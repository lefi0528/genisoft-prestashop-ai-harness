<?php
declare(strict_types=1);

namespace Genisoft\PrestashopHarness\Tools;

/**
 * PrestaShop Hook Safety Guard (PSR-4 Compliant)
 *
 * Checks the hook names a module registers (registerHook()) or implements (hook<Name>() methods)
 * against data/ps-hooks-reference.json, the hook names the core builds at runtime and the legacy
 * aliases of ps_hook_alias, and flags WordPress API calls. PHP files are read with token_get_all(),
 * so comments and strings are not mistaken for code.
 */
class PsHookGuard
{
    /**
     * Hook names the core builds at runtime. The catalog lists the instances found in real installs;
     * these patterns accept the others (a module's own ObjectModel class, grid or form).
     */
    private const DYNAMIC_HOOKS = [
        // classes/ObjectModel.php: 'actionObject' . <class> . 'AddAfter', ...
        '/^actionObject[A-Z]\w*(Add|Update|Delete|Duplicate)(Before|After)$/',
        // src/Core/Form/IdentifiableObject/Builder/FormBuilder.php
        '/^action[A-Z]\w*Form(BuilderModifier|DataProviderData|DataProviderDefaultData)$/',
        // src/Core/Grid: definition factory, query builder, data factory, filter form, presenter
        '/^action[A-Z]\w*Grid(Definition|QueryBuilder|Data|FilterForm|Presenter)Modifier$/',
        // src/Core/Form/IdentifiableObject/Handler/FormHandler.php
        '/^action(Before|After)(Create|Update)[A-Z]\w*FormHandler$/',
        // classes/controller/AdminController.php (action<Class><Action>Before/After), the PostProcess hooks
        // of the Symfony admin controllers and the PrestaShop 9 FrontController (action<Class>InitBefore, ...)
        '/^action[A-Z]\w*Controller(\w*(Before|After)|SetVariables|SetMedia|FrontEndObject)$/',
        // classes/controller/AdminController.php: 'action' . $this->controller_name . 'FormModifier', ...
        '/^action[A-Z]\w*(FormModifier|ListingFieldsModifier|ListingResultsModifier|OptionsModifier)$/',
        // src/Core/Kpi/Row/HookableKpiRowFactory.php
        '/^action[A-Z]\w*KpiRowModifier$/',
    ];

    /** WordPress API functions: no PrestaShop module calls them. Any wp_* or woocommerce_* call is flagged too. */
    private const WP_FUNCTIONS = ['add_action', 'add_filter', 'remove_action', 'remove_filter', 'do_action', 'apply_filters'];
    private const WP_PREFIXES = ['wp_', 'woocommerce_'];
    /** WordPress action names, flagged when registered as hooks (init() as a method name is legitimate). */
    private const WP_HOOK_NAMES = ['init', 'admin_init', 'save_post', 'the_content'];

    /** Calls that execute a hook: Hook::exec('name') and the hook dispatcher methods. */
    private const EXEC_STATIC = ['exec', 'execwithoutcache'];
    private const EXEC_METHODS = ['dispatchwithparameters', 'dispatchhook', 'dispatchhookwithparameters', 'dispatchforparameters', 'renderforparameters'];
    /** Functions whose result holds the hook names of their array arguments. */
    private const PASS_THROUGH = ['array_merge', 'array_merge_recursive', 'array_values', 'array_unique', 'array_filter', 'array_reverse'];

    /** @var array<string, string> lower-case hook name => catalog spelling */
    private array $hooks = [];
    /** @var array<string, true> exact catalog spellings (DisplayOverrideTemplate and displayOverrideTemplate both exist) */
    private array $spellings = [];
    /** @var array<string, array{0: string, 1: string}> lower-case alias => [alias, modern hook name] */
    private array $aliases = [];
    /** @var array<string, true> lower-case names of the hooks the scanned module executes itself, see prepare() */
    private array $executedByModule = [];

    /** Tokens of the PHP code last passed to parse(): [id or character, text, line], without whitespace and comments. */
    private array $t = [];
    /** @var list<array{name: ?string, line: int, params: array{0: int, 1: int}, start: int, end: int}> */
    private array $functions = [];
    /** @var list<array{0: int, 1: int}> class body token ranges */
    private array $classes = [];
    /** @var list<array{value: ?string, key: ?string, expr: array{0: int, 1: int}, start: int, end: int}> */
    private array $loops = [];
    /** @var array<string, array<string, list<array{0: int, 1: int}>>> scope => variable => assigned expressions */
    private array $variables = [];
    /** @var array<string, array{0: int, 1: int}> class constant => expression */
    private array $constants = [];
    /** @var array<string, list<array{0: int, 1: int}>> property => default value and $this->property assignments */
    private array $properties = [];
    /** @var array<string, true> lookups in progress, see resolveOnce() */
    private array $visiting = [];

    public function __construct(string $catalogPath)
    {
        if (!is_file($catalogPath)) {
            throw new \RuntimeException("Hook catalog not found: {$catalogPath}");
        }
        $raw = (string) file_get_contents($catalogPath);
        $catalog = json_decode($raw, true);
        // An empty or unreadable catalog would flag every hook as hallucinated: refuse to run instead.
        if (!is_array($catalog) || $catalog === [] || array_is_list($catalog)) {
            throw new \RuntimeException("Hook catalog is empty or not a JSON object keyed by hook name: {$catalogPath}");
        }
        foreach ($catalog as $name => $entry) {
            $name = (string) $name;
            if (is_array($entry) && isset($entry['alias_of'])) {
                $this->aliases[strtolower($name)] = [$name, (string) $entry['alias_of']];
            } else {
                $this->hooks[strtolower($name)] ??= $name;
                $this->spellings[$name] = true;
            }
        }
        if ($this->hooks === []) {
            throw new \RuntimeException("Hook catalog holds aliases only: {$catalogPath}");
        }
    }

    public function getHookCount(): int
    {
        return count($this->spellings);
    }

    public function getAliasCount(): int
    {
        return count($this->aliases);
    }

    /**
     * Indexes the hooks the module executes itself (Hook::exec('name'), {hook h='name'}, renderhook('name')),
     * so that a custom hook registered in one file and executed in another is recognised.
     *
     * @param string[] $files .php, .tpl and .twig files of the module
     */
    public function prepare(array $files): void
    {
        $this->executedByModule = [];
        foreach ($files as $file) {
            $code = (string) @file_get_contents($file);
            if (preg_match('/\.php$/i', $file)) {
                $this->parse($code);
                $names = $this->executedHooks();
            } else {
                $names = self::templateHooks($code);
            }
            foreach ($names as $name) {
                $this->executedByModule[strtolower($name)] = true;
            }
        }
    }

    public function validateFile(string $filePath): array
    {
        if (!is_file($filePath)) {
            return [['severity' => 'ERROR', 'category' => 'FILE_NOT_FOUND', 'message' => "File not found: {$filePath}"]];
        }

        $this->parse((string) file_get_contents($filePath));
        $executed = $this->executedByModule;
        foreach ($this->executedHooks() as $name) {
            $executed[strtolower($name)] = true;
        }

        $issues = $this->wordPressCalls();
        foreach ($this->registrations() as [$hook, $line]) {
            $issues[] = $this->verifyHookName($hook, false, $line, $executed);
        }
        foreach ($this->functions as $function) {
            if ($function['name'] !== null && preg_match('/^hook(\w+)$/i', $function['name'], $m)) {
                $issues[] = $this->verifyHookName(lcfirst($m[1]), true, $function['line'], $executed);
            }
        }

        $unique = [];
        foreach (array_filter($issues) as $issue) {
            $unique[$issue['line'] . '|' . $issue['category'] . '|' . $issue['message']] = $issue;
        }

        return array_values($unique);
    }

    /**
     * @param array<string, true> $executed lower-case names of the hooks the module executes itself
     */
    private function verifyHookName(string $hookName, bool $isMethod, int $line, array $executed): ?array
    {
        $lower = strtolower($hookName);
        $method = 'hook' . ucfirst($hookName) . '()';
        $issue = static function (string $severity, string $category, string $message) use ($line): array {
            return ['severity' => $severity, 'category' => $category, 'message' => $message, 'line' => $line];
        };

        if (isset($this->hooks[$lower])) {
            $official = $this->hooks[$lower];
            // PHP method names are case-insensitive, so only registerHook() spellings are reported.
            if (!$isMethod && !isset($this->spellings[$hookName])) {
                return $issue('WARNING', 'HOOK_CASING', "Hook casing mismatch: '{$hookName}'. Official casing is '{$official}'.");
            }
            return null;
        }

        if (isset($this->aliases[$lower])) {
            [$alias, $modern] = $this->aliases[$lower];
            return $isMethod
                ? $issue('WARNING', 'HOOK_ALIAS', "Method {$method} implements '{$alias}', a legacy alias of '{$modern}'. PrestaShop still calls it; name it hook" . ucfirst($modern) . '() instead.')
                : $issue('WARNING', 'HOOK_ALIAS', "'{$hookName}' is a legacy alias of '{$modern}' (ps_hook_alias). PrestaShop still maps it; register '{$modern}' instead.");
        }

        foreach (self::DYNAMIC_HOOKS as $pattern) {
            if (preg_match($pattern, $hookName)) {
                return null;
            }
        }

        if (in_array($lower, self::WP_HOOK_NAMES, true) || self::hasWpPrefix($lower)) {
            return $issue('CRITICAL', 'WP_HALLUCINATION', $isMethod
                ? "Method {$method} implements the WordPress hook '{$hookName}', which PrestaShop never executes."
                : "WordPress hook '{$hookName}' registered in PrestaShop module code.");
        }

        if (isset($executed[$lower])) {
            return $issue('WARNING', 'CUSTOM_HOOK', "'{$hookName}' is not a PrestaShop core hook, but this module executes it itself (Hook::exec or {hook h=...}): treated as the module's own hook. Check that the name is intended.");
        }

        $closest = null;
        $shortest = 4;
        foreach ($this->hooks as $official) {
            $dist = levenshtein($lower, strtolower($official));
            if ($dist <= $shortest) {
                $closest = $official;
                $shortest = $dist;
            }
        }
        $suggestion = $closest ? " Did you mean '{$closest}'?" : '';

        return $issue('ERROR', 'HALLUCINATED_HOOK', ($isMethod
            ? "Method {$method} implements an unknown or hallucinated hook '{$hookName}'."
            : "Unknown or hallucinated hook '{$hookName}'.") . $suggestion);
    }

    private static function hasWpPrefix(string $lowerName): bool
    {
        foreach (self::WP_PREFIXES as $prefix) {
            if (str_starts_with($lowerName, $prefix)) {
                return true;
            }
        }
        return false;
    }

    /** @return string[] hook names executed by {hook h='...'} (Smarty) or renderhook('...') (Twig) */
    private static function templateHooks(string $code): array
    {
        preg_match_all('/\{hook\s+h\s*=\s*([\'"])(\w+)\1/', $code, $smarty);
        preg_match_all('/render(?:hook|hooksarray)\s*\(\s*([\'"])(\w+)\1/', $code, $twig);
        return array_merge($smarty[2], $twig[2]);
    }

    // The methods below read the tokens of the PHP code last passed to parse().

    /** @return string[] hook names executed with Hook::exec('x') or $dispatcher->dispatchWithParameters('x', ...) */
    private function executedHooks(): array
    {
        $names = [];
        foreach ($this->t as $i => $tok) {
            if ($tok[0] !== T_STRING || !$this->is($i + 1, '(')) {
                continue;
            }
            $lower = strtolower($tok[1]);
            $static = in_array($lower, self::EXEC_STATIC, true) && $this->isHookClassCall($i);
            $method = in_array($lower, self::EXEC_METHODS, true) && $this->isObjectOperator($i - 1);
            if ($static || $method) {
                $args = $this->arguments($i + 1);
                if ($args !== []) {
                    $names = array_merge($names, $this->resolve($args[0][0], $args[0][1])['values']);
                }
            }
        }
        return $names;
    }

    /** @return list<array{severity: string, category: string, message: string, line: int}> */
    private function wordPressCalls(): array
    {
        $issues = [];
        foreach ($this->t as $i => $tok) {
            if (!in_array($tok[0], [T_STRING, T_NAME_FULLY_QUALIFIED], true) || !$this->is($i + 1, '(')) {
                continue;
            }
            // Method calls, static calls, declarations and `new` are not calls to WordPress functions.
            if ($this->isObjectOperator($i - 1) || $this->is($i - 1, T_DOUBLE_COLON) || $this->is($i - 1, T_FUNCTION) || $this->is($i - 1, T_NEW)) {
                continue;
            }
            $name = strtolower(ltrim($tok[1], '\\'));
            if (in_array($name, self::WP_FUNCTIONS, true) || self::hasWpPrefix($name)) {
                $issues[] = ['severity' => 'CRITICAL', 'category' => 'WP_HALLUCINATION', 'line' => $tok[2],
                    'message' => "WordPress function '{$name}()' called in PrestaShop module code. PrestaShop modules use registerHook() and hook<Name>() methods."];
            }
        }
        return $issues;
    }

    /** @return list<array{0: string, 1: int}> [hook name, line] for each hook name passed to registerHook() */
    private function registrations(): array
    {
        $found = [];
        foreach ($this->t as $i => $tok) {
            if ($tok[0] !== T_STRING || strtolower($tok[1]) !== 'registerhook' || !$this->is($i + 1, '(') || $this->is($i - 1, T_FUNCTION)) {
                continue;
            }
            $args = $this->arguments($i + 1);
            // Hook::registerHook($module, 'name') takes the hook name second.
            $index = $this->isHookClassCall($i) ? 1 : 0;
            if (!isset($args[$index])) {
                continue;
            }
            foreach (array_unique($this->resolve($args[$index][0], $args[$index][1])['values']) as $hook) {
                $found[] = [$hook, $tok[2]];
            }
        }
        return $found;
    }

    /**
     * Tokenizes the code and indexes the functions, classes and foreach loops, and the assignments that can
     * hold hook names: variables, class constants and properties.
     */
    private function parse(string $code): void
    {
        $this->t = [];
        $line = 1;
        foreach (token_get_all($code) as $tok) {
            if (is_array($tok)) {
                if ($tok[0] === T_CLOSE_TAG) {
                    $this->t[] = [';', ';', $tok[2]];
                } elseif (!in_array($tok[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT, T_OPEN_TAG, T_OPEN_TAG_WITH_ECHO, T_INLINE_HTML], true)) {
                    $this->t[] = $tok;
                }
                $line = $tok[2] + substr_count($tok[1], "\n");
            } else {
                $this->t[] = [$tok, $tok, $line];
            }
        }
        $this->functions = $this->classes = $this->loops = $this->variables = $this->constants = $this->properties = $this->visiting = [];

        $count = count($this->t);
        for ($i = 0; $i < $count; $i++) {
            $id = $this->t[$i][0];
            if ($id === T_FUNCTION) {
                $this->indexFunction($i);
            } elseif (($id === T_CLASS || $id === T_TRAIT) && !$this->is($i - 1, T_DOUBLE_COLON)) {
                $open = $this->next($i, '{');
                if ($open !== null) {
                    $this->classes[] = [$open, $this->close($open)];
                }
            } elseif ($id === T_FOREACH) {
                $this->indexLoop($i);
            }
        }
        for ($i = 0; $i < $count; $i++) {
            $tok = $this->t[$i];
            if ($tok[0] === T_CONST) {
                $this->indexConstants($i);
            } elseif ($tok[0] === T_VARIABLE && $tok[1] !== '$this') {
                $this->indexVariable($i);
            } elseif ($tok[0] === T_VARIABLE && $this->isObjectOperator($i + 1) && $this->is($i + 2, T_STRING) && $this->is($i + 3, '=')) {
                // $this->hooks = [...]; in a constructor or in install()
                $this->properties[$this->t[$i + 2][1]][] = [$i + 4, $this->statementEnd($i + 4)];
            }
        }
    }

    private function indexFunction(int $i): void
    {
        $j = $this->is($i + 1, '&') ? $i + 2 : $i + 1;
        $name = $this->is($j, T_STRING) ? $this->t[$j][1] : null;
        if (!$this->is($name === null ? $j : $j + 1, '(')) {
            return;
        }
        $paren = $name === null ? $j : $j + 1;
        $params = [$paren, $this->close($paren)];
        // Skip a closure's use (...) and the return type, up to the body (or ';' for an abstract method).
        for ($k = $params[1] + 1; $k < count($this->t); $k++) {
            if ($this->is($k, ';')) {
                return;
            }
            if ($this->is($k, '(')) {
                $k = $this->close($k);
            } elseif ($this->is($k, '{')) {
                $this->functions[] = ['name' => $name, 'line' => $this->t[$i][2], 'params' => $params, 'start' => $k, 'end' => $this->close($k)];
                return;
            }
        }
    }

    private function indexLoop(int $i): void
    {
        if (!$this->is($i + 1, '(')) {
            return;
        }
        $close = $this->close($i + 1);
        $as = null;
        $depth = 0;
        for ($k = $i + 2; $k < $close && $as === null; $k++) {
            $depth += $this->depthChange($k);
            if ($depth === 0 && $this->is($k, T_AS)) {
                $as = $k;
            }
        }
        if ($as === null) {
            return;
        }
        $vars = [];
        $hasKey = false;
        for ($k = $as + 1; $k < $close; $k++) {
            if ($this->is($k, T_VARIABLE)) {
                $vars[] = $this->t[$k][1];
            }
            $hasKey = $hasKey || $this->is($k, T_DOUBLE_ARROW);
        }

        $body = $close + 1;
        if ($this->is($body, '{')) {
            $end = $this->close($body);
        } elseif ($this->is($body, ':')) {
            $end = count($this->t) - 1;
            for ($k = $body; $k < count($this->t); $k++) {
                if ($this->is($k, T_ENDFOREACH)) {
                    $end = $k;
                    break;
                }
            }
        } else {
            $end = $this->statementEnd($body);
        }
        $this->loops[] = [
            'value' => $vars === [] ? null : end($vars),
            'key' => $hasKey && count($vars) >= 2 ? $vars[0] : null,
            'expr' => [$i + 2, $as],
            'start' => $body,
            'end' => $end,
        ];
    }

    private function indexConstants(int $i): void
    {
        // const A = [...], B = '...';  (PHP 8.3 allows a type between `const` and the name)
        $k = $i + 1;
        while ($k < count($this->t) && !$this->is($k, ';')) {
            if ($this->is($k, T_STRING) && $this->is($k + 1, '=')) {
                $end = $this->expressionEnd($k + 2);
                $this->constants[$this->t[$k][1]] = [$k + 2, $end];
                $k = $end;
                continue;
            }
            $k++;
        }
    }

    private function indexVariable(int $i): void
    {
        $name = $this->t[$i][1];
        $assign = $this->is($i + 1, '[') && $this->is($i + 2, ']') ? $i + 3 : $i + 1;   // $hooks[] = 'x';
        if (!$this->is($assign, '=')) {
            return;
        }
        $range = [$assign + 1, $this->expressionEnd($assign + 1)];
        foreach ($this->functions as $function) {
            if ($function['params'][0] < $i && $i < $function['params'][1]) {
                // Default value of a parameter: a variable of that function.
                $this->variables[(string) $function['start']][$name][] = $range;
                return;
            }
        }
        if ($this->functionAt($i) === null && $this->classAt($i)) {
            $this->properties[substr($name, 1)][] = $range;
        } else {
            $this->variables[$this->scopeKey($i)][$name][] = $range;
        }
    }

    /**
     * Collects the hook names an expression can hold: string literals used as values (alone or in an
     * array literal) and the values of the variables, constants, properties and methods it refers to.
     * Strings passed to other functions (Tools::getValue('hooks')) are ignored.
     *
     * @return array{values: string[], keys: string[]}
     */
    private function resolve(int $start, int $end): array
    {
        $values = [];
        $keys = [];
        $stack = [];   // kind of each open bracket: 'array' (literal), 'pass' (array_merge(...)) or 'other'
        for ($i = $start; $i < $end; $i++) {
            $tok = $this->t[$i];
            if ($tok[0] === '(' || $tok[0] === '[') {
                $stack[] = $this->bracketKind($i);
                continue;
            }
            if ($tok[0] === ')' || $tok[0] === ']') {
                array_pop($stack);
                continue;
            }
            $kind = $stack === [] ? 'array' : end($stack);
            if ($tok[0] === T_CONSTANT_ENCAPSED_STRING) {
                $value = substr($tok[1], 1, -1);
                if ($kind === 'array' && preg_match('/^[A-Za-z_]\w*$/', $value) && !$this->is($i - 1, '.') && !$this->is($i + 1, '.')) {
                    if (!$this->is($i + 1, T_DOUBLE_ARROW)) {
                        $values[] = $value;
                    } elseif (count(array_keys($stack, 'array', true)) <= 1) {
                        $keys[] = $value;   // keys of nested arrays are settings, not hook names
                    }
                }
                continue;
            }
            if ($kind === 'other') {
                continue;
            }
            $found = null;
            $last = $i;
            if ($tok[0] === T_VARIABLE && $tok[1] !== '$this') {
                if (!$this->is($i + 1, '[') && !$this->isObjectOperator($i + 1) && !$this->is($i + 1, '=')) {
                    $found = $this->resolveVariable($tok[1], $i);
                }
            } elseif ($tok[0] === T_VARIABLE && $this->isObjectOperator($i + 1) && $this->is($i + 2, T_STRING)) {
                $member = $this->t[$i + 2][1];
                $last = $this->is($i + 3, '(') ? $this->close($i + 3) : $i + 2;
                $found = $this->is($i + 3, '(') ? $this->resolveMethod($member) : $this->resolveProperty($member);
            } elseif (in_array($tok[0], [T_STRING, T_STATIC, T_NAME_FULLY_QUALIFIED, T_NAME_QUALIFIED], true) && $this->is($i + 1, T_DOUBLE_COLON) && isset($this->t[$i + 2])) {
                $member = $this->t[$i + 2];
                $last = $this->is($i + 3, '(') ? $this->close($i + 3) : $i + 2;
                if ($member[0] === T_VARIABLE) {
                    $found = $this->resolveProperty(substr($member[1], 1));
                } elseif ($member[0] === T_STRING && $this->is($i + 3, '(')) {
                    $found = $this->resolveMethod($member[1]);
                } elseif ($member[0] === T_STRING && isset($this->constants[$member[1]])) {
                    $found = $this->resolveOnce('const:' . $member[1], $this->constants[$member[1]]);
                }
            }
            // A reference concatenated with something else ($this->name . 'Banner') builds a name at runtime.
            if ($found !== null && !$this->is($i - 1, '.') && !$this->is($last + 1, '.')) {
                $values = array_merge($values, $found['values']);
                $keys = array_merge($keys, $found['keys']);
            }
            $i = $last;
        }

        return ['values' => $values, 'keys' => $keys];
    }

    /** @return array{values: string[], keys: string[]} */
    private function resolveVariable(string $name, int $at): array
    {
        // Inside foreach ($list as $key => $value), $value (or $key) comes from $list.
        $loops = array_filter($this->loops, static fn(array $loop): bool => $loop['start'] <= $at && $at <= $loop['end']);
        usort($loops, static fn(array $a, array $b): int => $b['start'] <=> $a['start']);
        foreach ($loops as $loop) {
            if ($loop['value'] === $name || $loop['key'] === $name) {
                $list = $this->resolveOnce('loop:' . $loop['start'], $loop['expr']);
                return ['values' => $loop['value'] === $name ? $list['values'] : $list['keys'], 'keys' => []];
            }
        }
        $scope = $this->scopeKey($at);
        return $this->resolveAll("var:{$scope}:{$name}", $this->variables[$scope][$name] ?? []);
    }

    /** @return array{values: string[], keys: string[]} */
    private function resolveProperty(string $name): array
    {
        return $this->resolveAll("prop:{$name}", $this->properties[$name] ?? []);
    }

    /**
     * A method returning a list, such as getHooks(): the expressions of its return statements.
     *
     * @return array{values: string[], keys: string[]}
     */
    private function resolveMethod(string $name): array
    {
        $returns = [];
        foreach ($this->functions as $function) {
            if ($function['name'] === null || strcasecmp($function['name'], $name) !== 0) {
                continue;
            }
            for ($k = $function['start'] + 1; $k < $function['end']; $k++) {
                if ($this->is($k, T_RETURN) && $this->functionAt($k) === $function) {
                    $returns[] = [$k + 1, $this->statementEnd($k + 1)];
                }
            }
        }
        return $this->resolveAll("method:{$name}", $returns);
    }

    /**
     * @param list<array{0: int, 1: int}> $ranges
     * @return array{values: string[], keys: string[]}
     */
    private function resolveAll(string $key, array $ranges): array
    {
        $result = ['values' => [], 'keys' => []];
        foreach ($ranges as $n => $range) {
            $found = $this->resolveOnce("{$key}:{$n}", $range);
            $result['values'] = array_merge($result['values'], $found['values']);
            $result['keys'] = array_merge($result['keys'], $found['keys']);
        }
        return $result;
    }

    /**
     * Resolves a range at most once per lookup chain, so that `$hooks = array_merge($hooks, [...])` cannot loop.
     *
     * @param array{0: int, 1: int} $range
     * @return array{values: string[], keys: string[]}
     */
    private function resolveOnce(string $key, array $range): array
    {
        if (isset($this->visiting[$key])) {
            return ['values' => [], 'keys' => []];
        }
        $this->visiting[$key] = true;
        $result = $this->resolve($range[0], $range[1]);
        unset($this->visiting[$key]);
        return $result;
    }

    private function bracketKind(int $i): string
    {
        $prev = $this->t[$i - 1] ?? [null, ''];
        if ($this->is($i, '[')) {
            // [ after a variable, a name or a closing bracket is an index access, otherwise an array literal.
            return in_array($prev[0], [T_VARIABLE, T_STRING, ']', ')', '}'], true) ? 'other' : 'array';
        }
        if ($prev[0] === T_ARRAY) {
            return 'array';
        }
        if (in_array($prev[0], [T_STRING, T_NAME_FULLY_QUALIFIED], true)
            && in_array(strtolower(ltrim($prev[1], '\\')), self::PASS_THROUGH, true)
            && !$this->isObjectOperator($i - 2) && !$this->is($i - 2, T_DOUBLE_COLON)) {
            return 'pass';
        }
        return 'other';
    }

    /** True for Hook::<name>( or HookCore::<name>( at token $i. */
    private function isHookClassCall(int $i): bool
    {
        return $this->is($i - 1, T_DOUBLE_COLON) && in_array(strtolower(ltrim($this->t[$i - 2][1] ?? '', '\\')), ['hook', 'hookcore'], true);
    }

    /** @return list<array{0: int, 1: int}> [start, end) token ranges of the arguments of the call opened at $open */
    private function arguments(int $open): array
    {
        $close = $this->close($open);
        $args = [];
        $start = $open + 1;
        $depth = 0;
        for ($k = $open + 1; $k < $close; $k++) {
            $depth += $this->depthChange($k);
            if ($depth === 0 && $this->is($k, ',')) {
                $args[] = [$start, $k];
                $start = $k + 1;
            }
        }
        if ($start < $close) {
            $args[] = [$start, $close];
        }
        return $args;
    }

    private function scopeKey(int $at): string
    {
        $function = $this->functionAt($at);
        return $function === null ? 'global' : (string) $function['start'];
    }

    private function functionAt(int $at): ?array
    {
        $inner = null;
        foreach ($this->functions as $function) {
            if ($function['start'] < $at && $at < $function['end'] && ($inner === null || $function['start'] > $inner['start'])) {
                $inner = $function;
            }
        }
        return $inner;
    }

    private function classAt(int $at): bool
    {
        foreach ($this->classes as [$open, $close]) {
            if ($open < $at && $at < $close) {
                return true;
            }
        }
        return false;
    }

    /** Index of the ';' (or of the unmatched closing bracket) that ends the statement starting at $start. */
    private function statementEnd(int $start): int
    {
        return $this->expressionEnd($start, false);
    }

    /** Like statementEnd(), but a top-level ',' also ends the expression when $comma is true (const A = 1, B = 2;). */
    private function expressionEnd(int $start, bool $comma = true): int
    {
        $depth = 0;
        for ($k = $start; $k < count($this->t); $k++) {
            $change = $this->depthChange($k);
            if ($change < 0 && $depth === 0) {
                return $k;
            }
            $depth += $change;
            if ($depth === 0 && ($this->is($k, ';') || ($comma && $this->is($k, ',')))) {
                return $k;
            }
        }
        return count($this->t);
    }

    private function depthChange(int $k): int
    {
        $id = $this->t[$k][0];
        if (in_array($id, ['(', '[', '{', T_CURLY_OPEN, T_DOLLAR_OPEN_CURLY_BRACES, T_ATTRIBUTE], true)) {
            return 1;
        }
        return in_array($id, [')', ']', '}'], true) ? -1 : 0;
    }

    /** Index of the bracket closing the one opened at $open (the last token if it is never closed). */
    private function close(int $open): int
    {
        $depth = 0;
        for ($k = $open; $k < count($this->t); $k++) {
            $depth += $this->depthChange($k);
            if ($depth === 0) {
                return $k;
            }
        }
        return count($this->t) - 1;
    }

    /** First index after $from holding $id, unless a ';' comes first. */
    private function next(int $from, int|string $id): ?int
    {
        for ($k = $from + 1; $k < count($this->t); $k++) {
            if ($this->is($k, $id)) {
                return $k;
            }
            if ($this->is($k, ';')) {
                return null;
            }
        }
        return null;
    }

    private function is(int $k, int|string $id): bool
    {
        return isset($this->t[$k]) && $this->t[$k][0] === $id;
    }

    private function isObjectOperator(int $k): bool
    {
        return $this->is($k, T_OBJECT_OPERATOR) || $this->is($k, T_NULLSAFE_OBJECT_OPERATOR);
    }
}
