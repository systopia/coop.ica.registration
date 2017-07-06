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
</table>



<div class="crm-submit-buttons">
{include file="CRM/common/formButtons.tpl" location="bottom"}
</div>


<script type="text/javascript">

var initial_line_count = {$line_count};
var max_line_count     = {$max_line_count};
var current_line_count = initial_line_count;
var role2amount        = {$role2amount};

{literal}
// hide all extra lines
function showLineCount(line_count) {
  for (var i = 1; i <= line_count; i++) {
    cj("tr.registration-participant-line-" + i).show();
  }
  for (var i = line_count+1; i <= max_line_count; i++) {
    cj("tr.registration-participant-line-" + i).hide();
  }
}

function increaseLineCount() {
  current_line_count++;
  showLineCount(current_line_count);
}

// call once initially
showLineCount(initial_line_count);

// FIXME: Test code!!
cj("[name=participant_role_1]").change(increaseLineCount);

// cj(".participant-role").change(blaa);
cj(".participant-role").each(function() {
 // do somethign for each
 // console.log(cj(this).val())
});



</script>
{/literal}