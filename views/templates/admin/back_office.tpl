<style>
    table.intrum-css {
        border-collapse: collapse;
    }
    table.intrum-css td {
        padding: 2px;
        border: 1px solid #DDDDDD;
    }
    tr.intrum-css-tr label {
        padding: 0 0 0 2px;
        width: auto;
    }

    tr.intrum-css-tr td {
        padding: 5px 2px 5px 2px;
        font-weight: bold;
    }
    .alert {
        padding: 8px 35px 8px 14px;
        margin-bottom: 20px;
        text-shadow: 0 1px 0 rgba(255, 255, 255, 0.5);
        background-color: #fcf8e3;
        border: 1px solid #fbeed5;
        -webkit-border-radius: 4px;
        -moz-border-radius: 4px;
        border-radius: 4px;
        width: 300px;
    }
    .alert-success {
        color: #468847;
        background-color: #dff0d8;
        border-color: #d6e9c6;
    }
    .cdp_plugin {
        margin: 0 0 5px 0;
        padding: 0;
    }

    #tabs1 {
        font: bold 11px/1.5em Verdana;
        float:left;
        width:100%;
        background:#FFFFFF;
        font-size:93%;
        line-height:normal;
    }
    #tabs1 ul {
        margin:0;
        padding:10px 10px 0 0px;
        list-style:none;
    }
    #tabs1 li {
        display:inline;
        margin:0;
        padding:0;
    }
    #tabs1 a {
        float:left;
        background:url("{$this_path}images/tableft1.gif") no-repeat left top;
        margin:0;
        padding:0 0 0 4px;
        text-decoration:none;
    }
    #tabs1 a span {
        float:left;
        display:block;
        background:url("{$this_path}images/tabright1.gif") no-repeat right top;
        padding:5px 15px 4px 6px;
        color:#627EB7;
    }
    /* Commented Backslash Hack hides rule from IE5-Mac \*/
    #tabs1 a span {
        float:none;
    }
    /* End IE5-Mac hack */
    #tabs a:hover span {
        color:#627EB7;
    }
    #tabs1 a:hover {
        background-position:0% -42px;
    }
    #tabs1 a:hover span {
        background-position:100% -42px;
    }
    #tab-settings, #tab-logs {
        padding: 5px;
        border: 1px solid #DDDDDD;
        clear: both;
        display: block;
    }
    {if ($intrum_show_log == 'true')}
    #tab-settings {
        display: none;
    }
    {/if}
    {if ($intrum_show_log == 'false')}
    #tab-logs {
        display: none;
    }

    {/if}
    table.table-logs {
        width: 100%;
        border-collapse: collapse;
    }
    table.table-logs td {
        padding: 3px;
        border: 1px solid #DDDDDD;

    }
</style>
{if ($upgrade_require == 1)}
<form method="post" action="{$smarty.server.REQUEST_URI|escape:'htmlall':'UTF-8'}">
		<div id="buttons">
			Upgrade module required. press Upgrade hook button.<br>
			<input type="hidden" value="upgradehook" name="upgradehook">
            <button name="intrum_confirm" value="0" type="submit">Upgrade hook</button>
        </div>
</form>
        {/if}
<h1 style="padding: 0; margin: 0">Intrum Justitia credit design platform module</h1>
<div id="tabs1">
    <ul>
        <li><a href="#" id="href-settings" title="Settings"><span>Settings</span></a></li>
        <li><a href="#" id="href-logs" title="Logs"><span>Logs</span></a></li>
    </ul>
</div>
<div id="tab-settings">
    <form method="post" action="{$smarty.server.REQUEST_URI|escape:'htmlall':'UTF-8'}" id="intrum_main_configuration">
        {if ($intrum_submit_main == 'OK')}
            <div class="alert alert-success">
                Main configuration saved
            </div>
        {/if}
        <table class="intrum-css">
            <tr class="intrum-css-tr">
                <td>
                    <label for="intrum_mode">Mode</label>
                </td>
                <td>
                    <select name="intrum_mode" id="intrum_mode">
                        <option value="test"{if ($intrum_mode == 'test')} selected{/if}>Test mode</option>
                        <option value="live"{if ($intrum_mode == 'live')} selected{/if}>Production mode</option>
                    </select>
                </td>
            </tr>
            <tr class="intrum-css-tr">
                <td>
                    <label for="intrum_client_id">Client ID</label>
                </td>
                <td>
                    <input type="text" name="intrum_client_id" id="intrum_client_id" value="{$intrum_client_id|escape}"/>
                </td>
            </tr>
            <tr class="intrum-css-tr">
                <td>
                    <label for="intrum_user_id">User ID</label>
                </td>
                <td>
                    <input type="text" name="intrum_user_id" id="intrum_user_id" value="{$intrum_user_id|escape}"/>
                </td>
            </tr>
            <tr class="intrum-css-tr">
                <td>
                    <label for="intrum_password">Password</label>
                </td>
                <td>
                    <input type="password" name="intrum_password" id="intrum_password" value="{$intrum_password|escape}"/>
                </td>
            </tr>
            <tr class="intrum-css-tr">
                <td>
                    <label for="intrum_tech_email">Technical Contact (E-mail)</label>
                </td>
                <td>
                    <input type="text" name="intrum_tech_email" id="intrum_tech_email" value="{$intrum_tech_email|escape}"/>
                </td>
            </tr>
            <tr class="intrum-css-tr">
                <td>
                    <label for="intrum_min_amount">Mininmal amount for credit check</label>
                </td>
                <td>
                    <input type="text" name="intrum_min_amount" id="intrum_min_amount" value="{$intrum_min_amount|escape}"/>
                </td>
            </tr>
            <tr class="intrum-css-tr">
                <td>
                    <label for="intrum_enabletmx">Enable ThreatMetrix</label>
                </td>
                <td>
                    <select name="intrum_enabletmx" id="intrum_enabletmx">
                        <option value="false"{if ($intrum_enabletmx == 'false')} selected{/if}>Disabled</option>
                        <option value="true"{if ($intrum_enabletmx == 'true')} selected{/if}>Enabled</option>
                    </select>
                </td>
            </tr>
            <tr class="intrum-css-tr">
                <td>
                    <label for="intrum_tmxorgid">ThreatMetrix orgid</label>
                </td>
                <td>
                    <input type="text" name="intrum_tmxorgid" id="intrum_tmxorgid" value="{$intrum_tmxorgid|escape}"/>
                </td>
            </tr>
        </table>
        <br/>

        <div id="buttons">
            <input type="hidden" name="submitIntrumMain" value="intrum_main_configuration"/>
            <button name="intrum_confirm" value="0" type="submit">Save main configuration</button>
        </div>
    </form>
    <br/>
    <br/>

    <form method="post" action="{$smarty.server.REQUEST_URI|escape:'htmlall':'UTF-8'}"
          id="intrum_payments_configuration">
        {if ($intrum_submit_payments == 'OK')}
            <div class="alert alert-success">
                Payments configuration saved
            </div>
        {/if}
        <table class="intrum-css">
            <tr class="intrum-css-tr">
                <td>Feedback from the credit check</td>
                <td>Select payment methods to hide</td>
            </tr>
            <tr>
                <td>There are serious negative indicators (status 1)</td>
                <td>{$payment_methods[1]["false"]}</td>
            </tr>
            <tr>
                <td>All payment methods (status 2)</td>
                <td>{$payment_methods[2]["false"]}</td>
            </tr>
            <tr>
                <td>Manual post-processing (currently not yet in use) (status 3)</td>
                <td>{$payment_methods[3]["false"]}</td>
            </tr>
            <tr>
                <td>Postal address is incorrect (status 4)</td>
                <td>{$payment_methods[4]["false"]}</td>
            </tr>
            <tr>
                <td>Enquiry exceeds the credit limit (the credit limit is specified in the cooperation agreement) (status 5)</td>
                <td>{$payment_methods[5]["false"]}</td>
            </tr>
            <tr>
                <td>Customer specifications not met (optional) (status 6)</td>
                <td>{$payment_methods[6]["false"]}</td>
            </tr>
            <tr>
                <td>Enquiry exceeds the net credit limit (enquiry amount plus open items exceeds credit limit) (status 7)</td>
                <td>{$payment_methods[7]["false"]}</td>
            </tr>
            <tr>
                <td>Person queried is not of creditworthy age (status 8)</td>
                <td>{$payment_methods[8]["false"]}</td>
            </tr>
            <tr>
                <td>Delivery address does not match invoice address (for payment guarantee only) (status 9)</td>
                <td>{$payment_methods[9]["false"]}</td>
            </tr>
            <tr>
                <td>Household cannot be identified at this address (status 10)</td>
                <td>{$payment_methods[10]["false"]}</td>
            </tr>
            <tr>
                <td>Country is not supported (status 11)</td>
                <td>{$payment_methods[11]["false"]}</td>
            </tr>
            <tr>
                <td>Party queried is not a natural person (status 12)</td>
                <td>{$payment_methods[12]["false"]}</td>
            </tr>
            <tr>
                <td>System is in maintenance mode (status 13)</td>
                <td>{$payment_methods[13]["false"]}</td>
            </tr>
            <tr>
                <td>Address with high fraud risk (status 14)</td>
                <td>{$payment_methods[14]["false"]}</td>
            </tr>
            <tr>
                <td>Allowance is too low (status 15)</td>
                <td>{$payment_methods[15]["false"]}</td>
            </tr>
            <tr>
                <td>Application data incomplete (status 16)</td>
                <td>{$payment_methods[16]["false"]}</td>
            </tr>
            <tr>
                <td>Send contract documents for external credit check (status 17)</td>
                <td>{$payment_methods[17]["false"]}</td>
            </tr>
            <tr>
                <td>External credit check in progress (status 18)</td>
                <td>{$payment_methods[18]["false"]}</td>
            </tr>
            <tr>
                <td>Customer is on client blacklist (status 19)</td>
                <td>{$payment_methods[19]["false"]}</td>
            </tr>
            <tr>
                <td>Customer is on client whitelist (status 20)</td>
                <td>{$payment_methods[20]["false"]}</td>
            </tr>
            <tr>
                <td>Customer is on Intrum blacklist (status 21)</td>
                <td>{$payment_methods[21]["false"]}</td>
            </tr>
            <tr>
                <td>Address is a P.O. box (status 22)</td>
                <td>{$payment_methods[22]["false"]}</td>
            </tr>
            <tr>
                <td>Address not in residential area (status 23)</td>
                <td>{$payment_methods[23]["false"]}</td>
            </tr>
            <tr>
                <td>Ordering person not legitimated to order for company (status 24)</td>
                <td>{$payment_methods[24]["false"]}</td>
            </tr>
            <tr>
                <td>IP Address temporarily blacklisted (status 25)</td>
                <td>{$payment_methods[25]["false"]}</td>
            </tr>
            <tr>
                <td>Accepted with right of withdrawal within 24 hours (status 27)</td>
                <td>{$payment_methods[27]["false"]}</td>
            </tr>
            <tr>
                <td>Credit Decision ok / Risk transferred to client due to exceeded credit limit (status 28)</td>
                <td>{$payment_methods[28]["false"]}</td>
            </tr>
            <tr>
                <td>Maximum client limit exceeded (status 29)</td>
                <td>{$payment_methods[29]["false"]}</td>
            </tr>
            <tr>
                <td>Unpaid overdue invoices exist (status 30)</td>
                <td>{$payment_methods[30]["false"]}</td>
            </tr>
            <tr>
                <td>Blacklist WSNP (NL only) (status 50)</td>
                <td>{$payment_methods[50]["false"]}</td>
            </tr>
            <tr>
                <td>Blacklist Bankruptcy (NL only) (status 51)</td>
                <td>{$payment_methods[51]["false"]}</td>
            </tr>
            <tr>
                <td>Blacklist Fraud (NL only) (status 52)</td>
                <td>{$payment_methods[52]["false"]}</td>
            </tr>
            <tr>
                <td>Front Door Limit exceeded (NL only) (status 53)</td>
                <td>{$payment_methods[53]["false"]}</td>
            </tr>
            <tr>
                <td>Consumer Limit exceeded (status 54)</td>
                <td>{$payment_methods[54]["false"]}</td>
            </tr>
            <tr>
                <td>Below minimal order amount (NL only) (status 55)</td>
                <td>{$payment_methods[55]["false"]}</td>
            </tr>
            <tr>
                <td>Person has open collection cases (NL only) (status 56)</td>
                <td>{$payment_methods[56]["false"]}</td>
            </tr>
            <tr>
                <td>Rejected based on third party information provider (status 57)</td>
                <td>{$payment_methods[57]["false"]}</td>
            </tr>
            <tr>
                <td style="border-top: 2px solid #555555">Fail to connect (status Error)</td>
                <td style="border-top: 2px solid #555555">{$payment_methods[0]["false"]}</td>
            </tr>
        </table>
        <br/>

        <div id="buttons">
            <input type="hidden" name="submitIntrumMethods" value="intrum_payments_configuration"/>
            <button name="intrum_confirm" value="0" type="submit">Save payments configuration</button>
        </div>
    </form>
</div>
<div id="tab-logs">
    <div>
        Searh in log
        <form method="post" action="{$smarty.server.REQUEST_URI|escape:'htmlall':'UTF-8'}">
            <input value="{$search_in_log|escape}" name="searchInLog"> <input type="submit" value="search">
            <input type="hidden" value="ok" name="submitLogSearch">
        </form>
    </div><br />
    {if !$search_in_log}Last 20 results
    {else}
        Search result for string "{$search_in_log|escape}"
    {/if}
    <table class="table-logs">
        <tr>
            <td>Firstname</td>
            <td>Lastname</td>
            <td>IP</td>
            <td>Status</td>
            <td>Date</td>
            <td>Request ID</td>
            <td>Type</td>
        </tr>
    {foreach from=$intrum_logs item=log}
        <tr>
            <td>{$log.firstname|escape}</td>
            <td>{$log.lastname|escape}</td>
            <td>{$log.ip|escape}</td>
            <td>{if ($log.status === '0')}Error{else}{$log.status|escape}{/if}</td>
            <td>{$log.creation_date|escape}</td>
            <td>{$log.request_id|escape}</td>
            <td>{$log.type|escape}</td>
        </tr>
    {/foreach}
    {if !$intrum_logs}
        <tr>
            <td colspan="5" style="padding: 10px">
                No results found
            </td>
        </tr>
    {/if}
    </table>
</div>
<script>
    $("#href-settings").click(function(e) {
        $("#tab-logs").hide();
        $("#tab-settings").show();
    });
    $("#href-logs").click(function(e) {
        $("#tab-settings").hide();
        $("#tab-logs").show();
    });
</script>