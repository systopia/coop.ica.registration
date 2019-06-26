<div style="text-align:center; vertical-align:middle;">
{if $error}
<h3>Failed to send confirmation email</h3>
<p>Error was "{$error}"</p>
{else}
<h3>Confirmation Email Sent</h3>
<p>The email was sent to the following email addresses:</p>
{foreach from=$sent_to item=email}
  <p>{$email}</p>
{/foreach}
{/if}
</div>