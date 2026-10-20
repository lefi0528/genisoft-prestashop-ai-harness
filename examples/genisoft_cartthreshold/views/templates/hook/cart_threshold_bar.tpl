{*
* 2026 Genisoft - PrestaShop Harness Engineering
*
* NOTICE OF LICENSE: MIT
*}
<div id="genisoft-cart-threshold" data-refresh-url="{$genisoft_refresh_url|escape:'html':'UTF-8'}">
{if $genisoft_bar}
    <div id="genisoft-cart-threshold-bar" class="genisoft-threshold-bar-wrapper">
        <div class="genisoft-threshold-message">
            {if $genisoft_bar.reached}
                <span class="genisoft-success-msg">
                    🎉 {l s='Congratulations! You unlocked free shipping.' d='Modules.Genisoftcartthreshold.Shop'}
                </span>
            {else}
                <span class="genisoft-pending-msg">
                    {l s='Add %s more to unlock free shipping!' sprintf=[$genisoft_bar.remaining] d='Modules.Genisoftcartthreshold.Shop'}
                </span>
            {/if}
        </div>
        <div class="genisoft-threshold-progress-bg">
            <div class="genisoft-threshold-progress-fill" style="width: {$genisoft_bar.progress_percent|escape:'html':'UTF-8'}%;"></div>
        </div>
        <div class="genisoft-threshold-amount-target">
            <strong>{$genisoft_bar.threshold|escape:'html':'UTF-8'}</strong>
        </div>
    </div>
{/if}
</div>
