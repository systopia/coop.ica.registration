{*-------------------------------------------------------+
| ICA Event Registration Module                          |
| Copyright (C) 2019 SYSTOPIA                            |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+-------------------------------------------------------*}


<div class="registration-checkin-search">
  <div class="crm-section">
    <div class="label">{$form.event_id.label}</div>
    <div class="content">{$form.event_id.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.registration_id.label}</div>
    <div class="content">{$form.registration_id.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.country_id.label}</div>
    <div class="content">{$form.country_id.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.participant_name.label}</div>
    <div class="content">{$form.participant_name.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.bage_name.label}</div>
    <div class="content">{$form.bage_name.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.organisation_name.label}</div>
    <div class="content">{$form.organisation_name.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.registered_with.label}</div>
    <div class="content">{$form.registered_with.html}</div>
    <div class="clear"></div>
  </div>
  <div class="crm-section">
    <div class="label">{$form.badge_status.label}</div>
    <div class="content">{$form.badge_status.html}</div>
    <div class="clear"></div>
  </div>
</div>

<div class="crm-submit-buttons">
{include file="CRM/common/formButtons.tpl" location="bottom"}
</div>

<br/>
{if $participants}
<div class="registration-checkin-results">
  <table class="row-highlight">
    <thead>
    <tr>
      <th>{ts domain="coop.ica.registration"}Participant{/ts}</th>
      <th>{ts domain="coop.ica.registration"}Status{/ts}</th>
      <th>{ts domain="coop.ica.registration"}Badge Type{/ts}</th>
      <th>{ts domain="coop.ica.registration"}Badge Color{/ts}</th>
      <th>{ts domain="coop.ica.registration"}Badge Status{/ts}</th>
      <th><!-- actions --></th>
    </tr>
    </thead>
    <tbody>
      {foreach from=$participants item=participant}
      <tr class="{cycle values="odd,even"}">
        <td>{$participant.sort_name}</td>
        <td>{$participant.status}</td>
        <td>{$participant.badge_type}</td>
        <td>{$participant.badge_color}</td>
        <td>{$participant.badge_status}</td>
        <td>
          <span>
            {foreach from=$participant.links item=link}{$link}{/foreach}
          </span>
        </td>
      </tr>
      {/foreach}
    </tbody>
  </table>
</div>

<div class="crm-submit-buttons">
  {include file="CRM/common/formButtons.tpl" location="bottom"}
</div>
{/if}

<script>
  {literal}
  // trigger page reload after edit
  cj(document).ready(function() {
    cj(document).on('crmPopupClose', function(event) {
      document.location.reload();
    });
  });
  {/literal}
</script>
