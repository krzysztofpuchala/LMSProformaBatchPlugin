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
 * Module: proformabatchconvert.php
 *
 * Batch conversion action
 */

// Wyczyść sesję zaznaczonych (nie potrzebujemy jej po konwersji)
$SESSION->remove('pbm');

// Pobierz wspólne parametry
$common_paytime = isset($_POST['common_paytime']) ? intval($_POST['common_paytime']) : null;
$common_paytype = isset($_POST['common_paytype']) ? intval($_POST['common_paytype']) : null;

// Walidacja
if ($common_paytime === null || $common_paytype === null) {
    $SESSION->redirect('?m=proformabatch&error=' . urlencode(trans('Payment time and payment type are required')));
}

if ($common_paytime < 0) {
    $SESSION->redirect('?m=proformabatch&error=' . urlencode(trans('Payment time cannot be negative')));
}

// Zbierz zaznaczone faktury TYLKO z POST (nie z sesji!)
$marks = array();
if (isset($_POST['marks']) && is_array($_POST['marks'])) {
    $marks = $_POST['marks'];
}

if (empty($marks)) {
    $SESSION->redirect('?m=proformabatch&error=' . urlencode(trans('No invoices selected')));
}

$ids = array_values($marks);

$converted_count = 0;
$errors = array();

foreach ($ids as $proforma_id) {
    try {
        // Sprawdź czy to faktycznie proforma
        $doc_type = $DB->GetOne('SELECT type FROM documents WHERE id = ?', array($proforma_id));

        if ($doc_type != DOC_INVOICE_PRO) {
            $errors[] = trans('Document $a is not a proforma invoice', $proforma_id);
            continue;
        }

        // Sprawdź czy już nie przekonwertowana
        $already_converted = $DB->GetOne(
            'SELECT id FROM documents WHERE type = ? AND reference = ?',
            array(DOC_INVOICE, $proforma_id)
        );
        if ($already_converted) {
            $errors[] = trans('Proforma $a already converted to invoice $b', $proforma_id, $already_converted);
            continue;
        }

        // Pobierz divisionid z proformy
        $divisionid = $DB->GetOne('SELECT divisionid FROM documents WHERE id = ?', array($proforma_id));

        if (empty($divisionid)) {
            $errors[] = trans('Proforma $a has no division assigned - cannot convert', $proforma_id);
            continue;
        }

        // Pobierz dane adresowe z vdivisions (ma address, city, zip z JOIN)
        $vdiv = $DB->GetRow(
            'SELECT address, city, zip, countryid
             FROM vdivisions WHERE id = ?',
            array($divisionid)
        );

        // Pobierz dane firmy z divisions (ma inv_header, inv_footer, etc.)
        $division_data = $DB->GetRow(
            'SELECT id as divisionid, name, shortname, ten, regon, bank, account,
                    inv_header, inv_footer, inv_author, inv_cplace
             FROM divisions WHERE id = ?',
            array($divisionid)
        );

        // Dodaj dane adresowe z vdivisions
        if ($vdiv) {
            $division_data['address'] = $vdiv['address'];
            $division_data['city'] = $vdiv['city'];
            $division_data['zip'] = $vdiv['zip'];
            $division_data['countryid'] = $vdiv['countryid'];
        }

        if (empty($division_data)) {
            $errors[] = trans('Division $a not found for proforma $b - cannot convert', $divisionid, $proforma_id);
            continue;
        }

        // KONWERSJA - użyj metody z LMS
        $invoice_id = $LMS->transformProformaInvoice($proforma_id);

        if (is_string($invoice_id)) {
            // Błąd (metoda zwraca string z komunikatem)
            $errors[] = $invoice_id;
            continue;
        }

        // Nadpisz paytime, paytype i dane sprzedawcy nowo utworzonej faktury
        // (transformProformaInvoice ma błąd - nie kopiuje prawidłowo danych sprzedawcy)

        // Pobierz currencyvalue z proformy
        $proforma_currencyvalue = $DB->GetOne('SELECT currencyvalue FROM documents WHERE id = ?', array($proforma_id));
        if (empty($proforma_currencyvalue)) {
            $proforma_currencyvalue = 1.0; // Domyślnie 1.0 dla PLN
        }

        // Aktualizuj dane faktury
        $sql = sprintf(
            "UPDATE documents SET
                paytime = %d, paytype = %d,
                currencyvalue = %s,
                divisionid = %d,
                div_name = %s, div_shortname = %s, div_address = %s, div_city = %s, div_zip = %s,
                div_countryid = %s, div_ten = %s, div_regon = %s, div_bank = %s, div_account = %s,
                div_inv_header = %s, div_inv_footer = %s, div_inv_author = %s, div_inv_cplace = %s
             WHERE id = %d",
            $common_paytime, $common_paytype,
            $proforma_currencyvalue,
            $division_data['divisionid'],
            $DB->Escape($division_data['name']),
            $DB->Escape($division_data['shortname']),
            $DB->Escape($division_data['address']),
            $DB->Escape($division_data['city']),
            $DB->Escape($division_data['zip']),
            $division_data['countryid'] ? $division_data['countryid'] : 'NULL',
            $DB->Escape($division_data['ten']),
            $DB->Escape($division_data['regon']),
            $division_data['bank'] ? $DB->Escape($division_data['bank']) : 'NULL',
            $DB->Escape($division_data['account']),
            $DB->Escape($division_data['inv_header']),
            $DB->Escape($division_data['inv_footer']),
            $DB->Escape($division_data['inv_author']),
            $DB->Escape($division_data['inv_cplace']),
            $invoice_id
        );

        $DB->Execute($sql);

        // WAŻNE: Upewnij się, że proforma została oznaczona jako closed
        // (transformProformaInvoice robi to jeśli phpui.default_preserve_proforma_invoice = true)
        $DB->Execute('UPDATE documents SET closed = 1 WHERE id = ?', array($proforma_id));

        if ($SYSLOG) {
            $SYSLOG->AddMessage(SYSLOG::RES_DOC, SYSLOG::OPER_UPDATE, array(
                SYSLOG::RES_DOC => $invoice_id,
                'paytime' => $common_paytime,
                'paytype' => $common_paytype,
            ));
        }

        $converted_count++;
    } catch (Exception $e) {
        $error_msg = trans('Error converting proforma $a: $b', $proforma_id, $e->getMessage());
        $errors[] = $error_msg;
    }
}

// Komunikat sukcesu/błędów
if ($converted_count > 0) {
    $SESSION->save('proformabatch_success', trans('Converted $a proforma invoices to VAT invoices', $converted_count));
}

if (!empty($errors)) {
    $SESSION->save('proformabatch_errors', $errors);
}

$SESSION->redirect('?m=proformabatch');
