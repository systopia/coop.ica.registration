/*-------------------------------------------------------+
| ICA Registration Extension                             |
| Copyright (C) 2017 SYSTOPIA                            |
| Author: B. Endres  (endres@systopia.de)                |
|         P. Batroff (batroff@systopia.de)               |
| http://www.systopia.de/                                |
+--------------------------------------------------------+
| This program is released as free software under the    |
| Affero GPL license. You can redistribute it and/or     |
| modify it under the terms of this license which you    |
| can read by viewing the included agpl.txt or online    |
| at www.gnu.org/licenses/agpl.html. Removal of this     |
| copyright header is strictly prohibited without        |
| written permission from the original author(s).        |
+-------------------------------------------------------*/

// hide fields ( #5569 )
cj(".crm-event-participantview-form-block-fee_amount").hide();
cj("#payment-info").parent().hide();

// add fee amount and level
var fee_level = "<tr> <td class='label'>Fee Level</td> <td> __FEE-LEVEL__ </td></tr>";
var fee_amount = "<tr> <td class='label'>Fee Amount</td> <td> __FEE-AMOUNT__ </td></tr>";
cj(fee_amount).insertAfter(cj(".crm-event-participantview-form-block-status"));
cj(fee_level).insertAfter(cj(".crm-event-participantview-form-block-status"));




