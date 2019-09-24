{*-------------------------------------------------------+
| SYSTOPIA XPortX Template                               |
| Copyright (C) 2018 SYSTOPIA                            |
| Author: B. Endres (endres@systopia.de)                 |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+-------------------------------------------------------*}
{literal}
<style>
  body {
    font-family: Helvetica, Arial, Geneva, sans-serif;
    padding-left: 20mm;
  }

  .container{
    page-break-before: always;
    position: relative;
    height: 165mm;
    width: 100mm;
  }
  .name_pos{
    position: absolute;
    top: 0mm;
  }
  .name{
    position: relative;
    height:45mm;
    font-size: 1.4em;
    font-weight: bold;
    display: table-cell;
    vertical-align: bottom;
    /* background-color: red; */
  }
  .organisation{
    position:absolute;
    top: 55mm;
    font-size: 1.1em;
  }
  .country_pos{
    position: absolute;
    top: 57mm;  /* 75mm - 20mm */
    /* background-color: green; */
  }
  .coutry{
    position:relative;
    height: 20mm;
    font-size: 1.1em;
    display: table-cell;
    vertical-align: bottom;
  }


</style>
{/literal}
{foreach from=$records item=record}
<div class="container" >
  <div class="name_pos">
    <div class="name"><span>{$record.Anzeigename}</span></div>
  </div>
  <div class="organisation">{$record.Anzeigeorganisationsname}</div>
  <div class="country_pos">
    <div class="coutry">SRI LANKA</div>
  </div>
</div>
{/foreach}
