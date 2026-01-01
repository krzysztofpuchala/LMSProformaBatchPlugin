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
 * ProformaBatchInitHandler
 *
 * Handler inicjalizacji pluginu do batch konwersji faktur proforma
 *
 * @author LMS Developer
 */
class ProformaBatchInitHandler
{
    /**
     * Dodaje katalog modules pluginu do ścieżki wyszukiwania modułów
     *
     * @param array $hook_data Hook data
     * @return array Hook data
     */
    public function modulesDirInit(array $hook_data = array())
    {
        $plugin_modules = PLUGINS_DIR . DIRECTORY_SEPARATOR .
            LMSProformaBatchPlugin::PLUGIN_DIRECTORY_NAME . DIRECTORY_SEPARATOR . 'modules';
        array_unshift($hook_data, $plugin_modules);
        return $hook_data;
    }

    /**
     * Dodaje katalog templates pluginu do Smarty
     *
     * @param Smarty $hook_data Hook data
     * @return \Smarty Hook data
     */
    public function smartyInit(Smarty $hook_data)
    {
        $template_dirs = $hook_data->getTemplateDir();
        $plugin_templates = PLUGINS_DIR . DIRECTORY_SEPARATOR .
            LMSProformaBatchPlugin::PLUGIN_DIRECTORY_NAME . DIRECTORY_SEPARATOR . 'templates';
        array_unshift($template_dirs, $plugin_templates);
        $hook_data->setTemplateDir($template_dirs);
        return $hook_data;
    }

    /**
     * Dodaje pozycję menu
     *
     * @param array $hook_data Hook data
     * @return array Hook data
     */
    public function menuInit(array $hook_data = array())
    {
        // Wstawienie do menu 'finances' po pozycji 'invoices'
        $menu_proformabatch = array(
            'proformabatch' => array(
                'name' => trans('Batch Convert Proforma'),
                'css' => 'lms-ui-icon-transform',
                'link' => '?m=proformabatch',
                'tip' => trans('Batch conversion of proforma invoices to VAT invoices'),
                'prio' => 45,
            ),
        );

        // Znajdź pozycję menu 'finances' i wstaw submenu
        if (isset($hook_data['finances']['submenu'])) {
            $submenu_keys = array_keys($hook_data['finances']['submenu']);
            $i = array_search('invoices', $submenu_keys);
            if ($i !== false) {
                // Wstaw po 'invoices'
                $hook_data['finances']['submenu'] = array_slice($hook_data['finances']['submenu'], 0, $i + 1, true)
                    + $menu_proformabatch
                    + array_slice($hook_data['finances']['submenu'], $i + 1, null, true);
            } else {
                // Fallback: dodaj na końcu
                $hook_data['finances']['submenu'] = array_merge(
                    $hook_data['finances']['submenu'],
                    $menu_proformabatch
                );
            }
        }

        return $hook_data;
    }

    /**
     * Dodaje uprawnienia dostępu
     */
    public function accessTableInit()
    {
        $access = AccessRights::getInstance();

        if (DBVERSION >= '2020060900') {
            $permission = new Permission(
                'proformabatch_access',
                trans('Batch convert proforma invoices'),
                '^proformabatch.*$',
                null,
                array('proformabatch' => Permission::MENU_ALL)
            );
        } else {
            $permission = new Permission(
                'proformabatch_access',
                trans('Batch convert proforma invoices'),
                '^proformabatch.*$'
            );
        }

        $access->insertPermission($permission, AccessRights::FIRST_FORBIDDEN_PERMISSION);
    }
}
