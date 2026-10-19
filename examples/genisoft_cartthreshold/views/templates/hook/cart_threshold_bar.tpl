{*
* 2026 Genisoft - PrestaShop Harness Engineering
*
* NOTICE OF LICENSE: MIT
*}
{if isset($genisoft_active) && $genisoft_active}
<div id="genisoft-cart-threshold-bar" class="genisoft-threshold-bar-wrapper">
    <div class="genisoft-threshold-message">
        {if $genisoft_reached}
            <span class="genisoft-success-msg">
                🎉 {l s='Congratulations! You unlocked Free Shipping.' d='Modules.Genisoft_cartthreshold.Shop'}
            </span>
        {else}
            <span class="genisoft-pending-msg">
                {l s='Add %s more to unlock Free Shipping!' sprintf=[$genisoft_remaining_formatted] d='Modules.Genisoft_cartthreshold.Shop'}
            </span>
        {/if}
    </div>
    <div class="genisoft-threshold-progress-bg">
        <div class="genisoft-threshold-progress-fill" style="width: {$genisoft_progress_percent|escape:'html':'UTF-8'}%;"></div>
    </div>
    <div class="genisoft-threshold-amount-target">
        <strong>{$genisoft_threshold_formatted|escape:'html':'UTF-8'}</strong>
    </div>
</div>
{/if}
