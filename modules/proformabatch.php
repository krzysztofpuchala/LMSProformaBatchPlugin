<?php

/*
 * LMS version 1.11-git
 *
 *  (C) Copyright 2001-2025 LMS Developers
 *
 *  Please, see the doc/AUTHORS for more information about authors!
 *
 *  This program is free software; you can redistribute it and/or modify
 *  it under the terms of the GNU General Public License Version 2 as
 *  published by the Free Software Foundation.
 *
 *  This program is distributed in the hope that it will be useful,
 *  but WITHOUT ANY WARRANTY; without even the implied warranty of
 *  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *  GNU General Public License for more details.
 *
 *  You should have received a copy of the GNU General Public License
 *  along with this program; if not, write to the Free Software
 *  Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307,
 *  USA.
 *
 *  $Id$
 */

/* Plugin: LMSProformaBatchPlugin
 * Module: proformabatch.php
 *
 * Batch conversion of proforma invoices to VAT invoices
 */

$layout['pagetitle'] = trans('Batch Convert Proforma Invoices');
$SESSION->save('backto', $_SERVER['QUERY_STRING']);

// Obsługa session dla zaznaczonych faktur
$SESSION->restore('pbm', $marks);
if (isset($_POST['marks'])) {
    foreach ($_POST['marks'] as $id => $mark) {
        $marks[$id] = $mark;
    }
}
$SESSION->save('pbm', $marks);

// Filtr miesiąca
if (isset($_POST['search'])) {
    $s = $_POST['search'];
} else {
    $SESSION->restore('pbs', $s);
}
if (!isset($s)) {
    $year = date("Y", time());
    $month = date("m", time());
    $s = $year . '/' . $month;
}
$SESSION->save('pbs', $s);

// Division filter (opcjonalnie)
if (isset($_POST['divisionid'])) {
    if (empty($_POST['divisionid'])) {
        $div = 0;
    } else {
        $div = $_POST['divisionid'];
    }
} else {
    $SESSION->restore('pbdiv', $div);
}
$SESSION->save('pbdiv', $div);

// Parsowanie miesiąca
if ($s && preg_match('/^[0-9]{4}\/[0-9]{2}$/', $s)) {
    list($year, $month) = explode('/', $s);
    $search_time = mktime(0, 0, 0, $month, 1, $year);
} else {
    $search_time = null;
}

// Pobranie listy faktur proforma
$params = array(
    'search' => $search_time,
    'cat' => 'month',
    'proforma' => 1,          // Tylko proformy
    'hideclosed' => 0,        // Pokaż również zamknięte
    'division' => $div,
    'order' => 'cdate,desc',
    'count' => false,
);

$proformalist = $LMS->GetInvoiceList($params);

// Dla każdej faktury dodaj:
// 1. Saldo klienta
// 2. Sprawdź czy ma już powiązaną fakturę VAT (reference)
if (!empty($proformalist)) {
    foreach ($proformalist as &$proforma) {
        // Saldo klienta
        $proforma['customer_balance'] = $LMS->GetCustomerBalance($proforma['customerid']);

        // Sprawdź czy już przekonwertowana (ma reference w documents)
        $proforma['has_vat_invoice'] = $DB->GetOne(
            'SELECT id FROM documents WHERE type = ? AND reference = ?',
            array(DOC_INVOICE, $proforma['id'])
        );

        // Dodaj pełny numer faktury
        $proforma['fullnumber'] = docnumber(array(
            'number' => $proforma['number'],
            'template' => $proforma['template'],
            'cdate' => $proforma['cdate'],
            'customerid' => $proforma['customerid'],
        ));
    }
    unset($proforma);
}

// Pobierz $PAYTYPES do selecta
global $PAYTYPES;

$SMARTY->assign('proformalist', $proformalist);
$SMARTY->assign('marks', $marks);
$SMARTY->assign('search', $s);
$SMARTY->assign('division', $div);
$SMARTY->assign('divisions', $LMS->GetDivisions());
$SMARTY->assign('paytypes', $PAYTYPES);
$SMARTY->display('proformabatch.html');
