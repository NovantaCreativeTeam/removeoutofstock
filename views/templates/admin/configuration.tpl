<div class="panel">
    <div class="panel-heading">
        {l s='Out of Stock Product visibility Configuration' mod='removeoutofstock'}
    </div>
    <form method="POST" action="{$action}">
        <div class="form-wrapper">
            <div class="form-group clearfix">
                <label class="control-label col-lg-4">{l s='Categories to handle visibility' mod='removeoutofstock'}</label>
                <div class="col-lg-8">
                    {$categories_tree}
                </div>
            </div>

            <div class="form-group clearfix">
                <label class="control-label col-lg-4">{l s='Disable product when Out of Stock' mod='removeoutofstock'}</label>
                <div class="col-lg-6">
                    <span class="switch prestashop-switch fixed-width-lg">
                        <input type="radio" name="remove_ofs_disable" id="remove_ofs_disable_on" value="1" {if $remove_ofs_disable}checked="checked"{/if}>
                        <label for="remove_ofs_disable_on">{l s='Yes' d='Admin.Global'}</label>
                        <input type="radio" name="remove_ofs_disable" id="remove_ofs_disable_off" value="0" {if !$remove_ofs_disable}checked="checked"{/if}>
                        <label for="remove_ofs_disable_off">{l s='No' d='Admin.Global'}</label>
                        <a class="slide-button btn"></a>
                    </span>
                </div>
            </div>

            <div class="form-group clearfix">
                <label class="control-label col-lg-4">{l s='Hide product when Out of Stock' mod='removeoutofstock'}</label>
                <div class="col-lg-6">
                    <span class="switch prestashop-switch fixed-width-lg">
                        <input type="radio" name="remove_ofs_hide" id="remove_ofs_hide_on" value="1" {if $remove_ofs_hide}checked="checked"{/if}>
                        <label for="remove_ofs_hide_on">{l s='Yes' d='Admin.Global'}</label>
                        <input type="radio" name="remove_ofs_hide" id="remove_ofs_hide_off" value="0" {if !$remove_ofs_hide}checked="checked"{/if}>
                        <label for="remove_ofs_hide_off">{l s='No' d='Admin.Global'}</label>
                        <a class="slide-button btn"></a>
                    </span>
                </div>
            </div>

            <div class="form-group clearfix">
                <label class="control-label col-lg-4">{l s='Category to move disabled product' mod='removeoutofstock'}</label>
                <div class="col-lg-2">
                    <select name="remove_ofs_cemetery_cat" id="remove_ofs_cemetery_cat">
                        <option value="">---</option>
                        {foreach $cemetery_categories as $cemetery_category}
                            <option value={$cemetery_category['id_category']} {if $cemetery_category['id_category'] == $remove_ofs_cemetery_cat}selected="selected"{/if}>
                                {$cemetery_category['name']}
                            </option>
                        {/foreach}
                    </select>
                </div>
            </div>

            <div class="form-group clearfix">
                <label class="control-label col-lg-4">{l s='States of order that renable products' mod='removeoutofstock'}</label>
                <div class="col-lg-2">
                    <select name="remove_ofs_back_states[]" id="remove_ofs_back_states" multiple="multiple">
                        {foreach $orders_states as $order_state}
                            <option value={$order_state['id_order_state']} {if in_array($order_state['id_order_state'], $remove_ofs_back_states) }selected="selected"{/if}>
                                {$order_state['name']}
                            </option>
                        {/foreach}
                    </select>
                </div>
            </div>

        </div>
        <div class="panel-footer">
            <button type="submit" name="submitConfiguration" value="1" class="btn btn-default pull-right">
                <i class="process-icon-save"></i> {l s='Save' mod='trovaprezzi'}
            </button>
        </div>
    </form>
</div>