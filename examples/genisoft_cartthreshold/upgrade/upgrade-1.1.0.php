<?php
declare(strict_types=1);

if (!defined('_PS_VERSION_')) {
    exit;
}

/**
 * 1.0.0 -> 1.1.0: stop listening to actionCartSave and drop the write-only log table.
 *
 * @param Genisoft_Cartthreshold $module
 */
function upgrade_module_1_1_0($module): bool
{
    $module->unregisterHook('actionCartSave');

    return Genisoft_Cartthreshold::dropLegacyLogTable();
}
