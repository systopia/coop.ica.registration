{*-------------------------------------------------------+
| ICA Event Registration Module                          |
| Copyright (C) 2017 SYSTOPIA                            |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+-------------------------------------------------------*}

<table>
{foreach from=$line_numbers item=line_number}
  {capture assign=participant_field}participant_id_{$line_number}{/capture}
  {capture assign=participant_role_field}participant_role_{$line_number}{/capture}
  {capture assign=participant_amount_field}participant_amount_{$line_number}{/capture}
  <tr class="registration-participant-line-{$line_number}">
    <td>
      <div class="crm-section">
        {$form.$participant_field.html}
      </div>
    </td>
    <td>
      <div class="crm-section">
        {$form.$participant_role_field.html}
      </div>
    </td>
    <td>
      <div class="crm-section">
        {$form.$participant_amount_field.html}
      </div>
    </td>
  </tr>
{/foreach}

{capture assign=contribution_status}contribution_status{/capture}
{capture assign=contribution_sum}contribution_sum{/capture}
{capture assign=contribution_sum_description}contribution_sum_description{/capture}
{capture assign=payment_method}payment_method{/capture}
  <tr class="accumulated_participant_line">
    <td>
      <div class="crm-section">
        <!-- <div class = description><strong>Contribution Sum: </strong></div> -->
          {$form.$contribution_sum_description.html}

      </div>
    </td>
  </tr>
  <tr>
    <td>
      <div class="crm-section">
          {$form.$contribution_status.html}
      </div>
    </td>
    <td>
      <div class="crm-section">
          {$form.$payment_method.html}
      </div>
    </td>
    <td>
      <div class="crm-section">
        {$form.$contribution_sum.html}
      </div>
    </td>
  </tr>
</table>

<div class="crm-submit-buttons">
{include file="CRM/common/formButtons.tpl" location="bottom"}
</div>

{* hidden vars *}
{$form.cid.html}

<script type="text/javascript">

var line_count = {$line_count};
var role2amount        = {$role2amount};

{literal}
////////////////////////////////////////////////////////////////////////////////

function updateAmounts() {
  for (var i = 1; i <= line_count; i++) {
    var role = cj("[name=participant_role_" + i + "]").val();
    cj("[name=participant_amount_" + i + "]").val(role2amount[role]);
  }
  calculate_accumulated_amount();
}

function updateContribStatus() {
    var status = cj("[name=contribution_status").val();
    cj("[name=contribution_status]").val(status);
}

function register_role_changes() {
  for (var i = 1; i <= line_count; i++) {
    cj("[name=participant_role_" + i + "]").change(updateAmounts);
  }
}

function register_contribution_status_changes() {
    cj("[name=contribution_status]").change(updateContribStatus);
}

function calculate_accumulated_amount() {
  var accumulated_amount = 0;
  for (var i = 1; i <= line_count; i++) {
    accumulated_amount += Number(cj("[name=participant_amount_" + i + "]").val());
  }
  cj("[name=contribution_sum]").val(accumulated_amount);
}
////////////////////////////////////////////////////////////////////////////////
updateAmounts();
calculate_accumulated_amount();
register_role_changes();
register_contribution_status_changes();

</script>
{/literal}
