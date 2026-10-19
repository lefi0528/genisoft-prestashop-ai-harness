<div class="user-profile">
    <h2>Welcome {$customer_name|escape:'html':'UTF-8'}</h2>
    <div class="banner">
        {$custom_banner|escape:'html':'UTF-8'}
    </div>
    <span class="year">{$smarty.now|date_format:'%Y'}</span>
    <button>{l s='Update Profile' d='Modules.Genisoftprofile.Shop'}</button>
</div>
