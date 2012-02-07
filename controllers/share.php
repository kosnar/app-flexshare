<?php

/**
 * Flexshare settings controller.
 *
 * @category   Apps
 * @package    Flexshare
 * @subpackage Controllers
 * @author     ClearFoundation <developer@clearfoundation.com>
 * @copyright  2011 ClearFoundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU General Public License version 3 or later
 * @link       http://www.clearfoundation.com/docs/developer/apps/flexshare/
 */

///////////////////////////////////////////////////////////////////////////////
//
// This program is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// This program is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with this program.  If not, see <http://www.gnu.org/licenses/>.
//
///////////////////////////////////////////////////////////////////////////////

///////////////////////////////////////////////////////////////////////////////
// D E P E N D E N C I E S
///////////////////////////////////////////////////////////////////////////////

// Exceptions
//-----------

use \clearos\apps\flexshare\Flexshare_Parameter_Not_Found_Exception as Flexshare_Parameter_Not_Found_Exception;

clearos_load_library('flexshare/Flexshare_Parameter_Not_Found_Exception');

///////////////////////////////////////////////////////////////////////////////
// C L A S S
///////////////////////////////////////////////////////////////////////////////

/**
 * Flexshare controller.
 *
 * @category   Apps
 * @package    Flexshare
 * @subpackage Controllers
 * @author     ClearFoundation <developer@clearfoundation.com>
 * @copyright  2011 ClearFoundation
 * @license    http://www.gnu.org/copyleft/gpl.html GNU General Public License version 3 or later
 * @link       http://www.clearfoundation.com/docs/developer/apps/flexshare/
 */

class Share extends ClearOS_Controller
{
    /**
     * Flexshare settings overview.
     *
     * @return view
     */

    function index($share)
    {
        // See comment in share.php
        if (empty($share))
            $share = $this->session->userdata('flexshare');

        $this->_form($share, 'view');
    }

    /**
     * Flexshare add view.
     *
     * @return view
     */

    function add()
    {
        $this->_form(NULL, 'add');
    }

    /**
     * Flexshare delete view.
     *
     * @param string $share share
     *
     * @return view
     */

    function delete($share)
    {
        $confirm_uri = '/app/flexshare/share/destroy/' . $share;
        $cancel_uri = '/app/flexshare';
        $items = array($share);

        $this->page->view_confirm_delete($confirm_uri, $cancel_uri, $items);
    }

    /**
     * Destroys Flexshare share.
     *
     * @param string $share share
     *
     * @return view
     */

    function destroy($share)
    {
        // Load libraries
        //---------------

        $this->load->library('flexshare/Flexshare');

        // Handle form submit
        //-------------------

        try {
            // Second parameter is option to delete folder
            // Default delete wizard doesn't allow options
            // Set to false (do not delete) for now
            $this->flexshare->delete_share($share, FALSE);
            $this->page->set_status_deleted();
            redirect('/flexshare');
        } catch (Exception $e) {
            $this->page->view_exception($e);
            return;
        }
    }

    /**
     * Flexshare edit view.
     *
     * @param string $share share
     *
     * @return view
     */

    function edit($share)
    {
        $this->_form($share, 'edit');
    }

    ///////////////////////////////////////////////////////////////////////////////
    // P R I V A T E
    ///////////////////////////////////////////////////////////////////////////////

    /**
     * Flexshare common add/edit/view form handler.
     *
     * @param string $share     share
     * @param string $form_type form type (add or edit)
     *
     * @return view
     */

    function _form($share, $form_type)
    {
        // Load libraries
        //---------------

        $this->lang->load('flexshare');
        $this->lang->load('groups');
        $this->lang->load('users');
        $this->load->library('flexshare/Flexshare');
        $this->load->library('groups/Group_Engine');
        $this->load->factory('groups/Group_Manager_Factory');
        $this->load->factory('users/User_Manager_Factory');

        // Avoid showing geeky Linux paths if a custom directory has not been configured
        //------------------------------------------------------------------------------

        $directories = array();

        try {
            $directories = $this->flexshare->get_dir_options($share);
        } catch (Flexshare_Parameter_Not_Found_Exception $e) {
            // This is OK
        } catch (Exception $e) {
            $this->page->view_exception($e);
            return;
        }

        if (count($directories) === 1) {
            $default_info = array_keys($directories);
            $default_directory = $default_info[0];
            $directory = $default_info[0];
        } else {
            $directory = $this->input->post('directory');
        }

        // Set validation rules
        //---------------------
         
        // Name cannot be set once added.
        $this->form_validation->set_policy('name', 'flexshare/Flexshare', 'validate_name', TRUE);
        $this->form_validation->set_policy('description', 'flexshare/Flexshare', 'validate_description', TRUE);
        $this->form_validation->set_policy('group', 'flexshare/Flexshare', 'validate_group', TRUE);

        if (count($directories) !== 1)
            $this->form_validation->set_policy('directory', 'flexshare/Flexshare', 'validate_directory', TRUE);

        $form_ok = $this->form_validation->run();

        // Handle form submit
        //-------------------

        if (($this->input->post('submit') && $form_ok)) {
            try {
                if ($form_type == 'edit') {
                    $this->flexshare->set_description($share, $this->input->post('description'));
                    $this->flexshare->set_group($share, $this->input->post('group'));
                    $this->flexshare->set_directory($share, $directory);
                    $this->flexshare->set_share_state($share, $this->input->post('enabled'));

                    redirect('/flexshare/summary/' . $share);
                } else {
                    $this->flexshare->add_share(
                        $this->input->post('name'),
                        $this->input->post('description'),
                        $this->input->post('group'),
                        $directory
                    );
                    $this->flexshare->set_share_state($this->input->post('name'), TRUE);

                    redirect('/flexshare/summary/' . $this->input->post('name'));
                }

            } catch (Exception $e) {
                $this->page->set_message(clearos_exception_message($e));
            }
        }

        // Load view data
        //---------------

        try {
            $data['form_type'] = $form_type;

            if ($form_type !== 'add') {
                $info = $this->flexshare->get_share($share);
                $data['name'] = $info['Name'];
                $data['description'] = $info['ShareDescription'];
                $data['group'] = $info['ShareGroup'];
                $data['directory'] = $info['ShareDir'];
                $data['enabled'] = ($info['ShareEnabled'] == 0) ? FALSE: TRUE;
            }

            // Directories
            $data['directories'] = $directories;

            if (count($directories) === 1)
                $data['use_default'] = TRUE;
            else
                $data['use_default'] = FALSE;

            // Groups
            $groups = $this->group_manager->get_details();
            // TODO: all flag in group_manager->get_details() to pull in allusers
            $group_options[-1] = lang('base_select');
            $group_options['allusers'] = 'allusers - ' . 'All Users'; // FIXME: translate

            foreach ($groups as $name => $group)
                $group_options[$name] = $name . ' - ' . $group['description'];

            $data['group_options'] = $group_options;

            // Available file share servers
            // TODO: use API call instead of file_exists
            $data['file_installed'] = (file_exists('/var/clearos/samba/initialized_local')) ? TRUE : FALSE;
            $data['web_installed'] = (clearos_library_installed('web/Httpd')) ? TRUE : FALSE;
            $data['ftp_installed'] = (clearos_library_installed('ftp/ProFTPd')) ? TRUE : FALSE;
        } catch (Exception $e) {
            $this->page->view_exception($e);
            return;
        }

        // Load the views
        //---------------

        $this->page->view_form('flexshare/share', $data, lang('flexshare_flexshares'));
    }
}
