<?php

/*
 *  LMS version 1.11-git
 *
 *  Copyright (C) 2001-2025 LMS Developers
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

/**
 * LMSProformaBatchPlugin
 *
 * Plugin do wsadowej konwersji faktur proforma na faktury VAT
 *
 * @author LMS Developer
 */
class LMSProformaBatchPlugin extends LMSPlugin
{
    const PLUGIN_DIRECTORY_NAME = 'LMSProformaBatchPlugin';
    const PLUGIN_NAME = 'Proforma Batch Converter';
    const PLUGIN_DESCRIPTION = 'Batch conversion of proforma invoices to VAT invoices';
    const PLUGIN_AUTHOR = 'PRO-Admin Puchala Krzysztof';

    public function registerHandlers()
    {
        $this->handlers = array(
            'modules_dir_initialized' => array(
                'class' => 'ProformaBatchInitHandler',
                'method' => 'modulesDirInit',
            ),
            'smarty_initialized' => array(
                'class' => 'ProformaBatchInitHandler',
                'method' => 'smartyInit',
            ),
            'menu_initialized' => array(
                'class' => 'ProformaBatchInitHandler',
                'method' => 'menuInit',
            ),
            'access_table_initialized' => array(
                'class' => 'ProformaBatchInitHandler',
                'method' => 'accessTableInit',
            ),
        );
    }
}
