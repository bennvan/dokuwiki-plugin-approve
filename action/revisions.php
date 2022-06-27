<?php

if(!defined('DOKU_INC')) die();

class action_plugin_approve_revisions extends DokuWiki_Action_Plugin {

    function register(Doku_Event_Handler $controller) {
		$controller->register_hook('HTML_REVISIONSFORM_OUTPUT', 'BEFORE', $this, 'handle_revisions', array());
        $controller->register_hook('FORM_REVISIONS_OUTPUT', 'BEFORE', $this, 'form_revisions_output', array());
	}

    function form_revisions_output (\Doku_Event $event, $param)
    {
        global $INFO;

        try {
            /** @var \helper_plugin_approve_db $db_helper */
            $db_helper = plugin_load('helper', 'approve_db');
            $sqlite = $db_helper->getDB();
        } catch (Exception $e) {
            msg($e->getMessage(), -1);
            return;
        }
        /** @var helper_plugin_approve $helper */
        $helper = plugin_load('helper', 'approve');

        if (!$helper->use_approve_here($sqlite, $INFO['id'])) return;

        $res = $sqlite->query('SELECT rev, approved, ready_for_approval, version
                                FROM revision
                                WHERE page=?', $INFO['id']);
        $approveRevisions = $sqlite->res2arr($res);
        $approveRevisions = array_combine(array_column($approveRevisions, 'rev'), $approveRevisions);
        
        /** @var dokuwiki\Form\Form $form */
        $form = $event->data;
        for ($pos = 0; $pos < $form->elementCount(); $pos++) {

            $element = $form->getElementAt($pos);
            $type    = $element->getType();

            if ($type == 'checkbox') {
                $revision = $element->val();
                $element->attr('disabled', '0');
                $ver = '';
                if (!isset($approveRevisions[$revision])) {
                    $class =  'plugin__approve_red';
                } elseif ($approveRevisions[$revision]['approved']) {
                    $class =  'plugin__approve_green';
                    $form->getElementAt($pos+1)->val('<span class="plugin__approve_version"> [Ver. '.$approveRevisions[$revision]['version'].'] </span>');
                } elseif ($this->getConf('ready_for_approval') && $approveRevisions[$revision]['ready_for_approval']) {
                    $class =  'plugin__approve_ready';
                } else {
                    $class =  'plugin__approve_red';
                }
                $form->getElementAt($pos-1)->addClass($class);
            }

        }
    }

    // Deprecated function (Igor)
	function handle_revisions(Doku_Event $event, $param) {
		global $INFO;

        try {
            /** @var \helper_plugin_approve_db $db_helper */
            $db_helper = plugin_load('helper', 'approve_db');
            $sqlite = $db_helper->getDB();
        } catch (Exception $e) {
            msg($e->getMessage(), -1);
            return;
        }
        /** @var helper_plugin_approve $helper */
        $helper = plugin_load('helper', 'approve');

        if (!$helper->use_approve_here($sqlite, $INFO['id'])) return;

        $res = $sqlite->query('SELECT rev, approved, ready_for_approval, version
                                FROM revision
                                WHERE page=?', $INFO['id']);
        $approveRevisions = $sqlite->res2arr($res);
        $approveRevisions = array_combine(array_column($approveRevisions, 'rev'), $approveRevisions);

		$member = null;
		foreach ($event->data->_content as $key => $ref) {
            if(isset($ref['_elem']) && $ref['_elem'] == 'opentag' && $ref['_tag'] == 'div' && $ref['class'] == 'li') {
                $member = $key;
            }

            if ($member && $ref['_elem'] == 'tag' &&
                $ref['_tag'] == 'input' && $ref['name'] == 'rev2[]'){
                $revision = $ref['value'];
                if ($revision == 'current') {
                    $revision = $INFO['meta']['date']['modified'];
                }
                if (!isset($approveRevisions[$revision])) {
                    $class =  'plugin__approve_red';
                } elseif ($approveRevisions[$revision]['approved']) {
                    $class =  'plugin__approve_green';
                } elseif ($this->getConf('ready_for_approval') && $approveRevisions[$revision]['ready_for_approval']) {
                    $class =  'plugin__approve_ready';
                } else {
                    $class =  'plugin__approve_red';
                }

                $event->data->_content[$member]['class'] = "li $class";

                $member = null;
            }
		}
	}

}
