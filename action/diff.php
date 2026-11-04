<?php

use dokuwiki\Extension\ActionPlugin;
use dokuwiki\Extension\Event;
use dokuwiki\Extension\EventHandler;
use dokuwiki\plugin\approve\ApprovalDiff;
use dokuwiki\plugin\approve\ApprovalHistory;

/** Add approval context to the existing core differences view. */
class action_plugin_approve_diff extends ActionPlugin
{
    public function register(EventHandler $controller)
    {
        $controller->register_hook('TPL_ACT_RENDER', 'BEFORE', $this, 'renderDiff');
    }

    public function renderDiff(Event $event)
    {
        global $INFO, $INPUT;
        if ($event->data !== 'diff' || !$event->mayRunDefault()) return;
        if (empty($INFO['exists']) || auth_quickaclcheck($INFO['id']) < AUTH_READ) return;

        try {
            $db = plugin_load('helper', 'approve_db')->getDB();
        } catch (Exception $e) {
            msg($e->getMessage(), -1);
            return;
        }
        $helper = plugin_load('helper', 'approve');
        if (!$helper->use_approve_here($db, $INFO['id'], $approver)) return;
        if (!$helper->client_can_see_drafts($INFO['id'], $approver)
            && !$helper->client_can_approve($INFO['id'], $approver)) return;

        $approvals = ApprovalHistory::loadApprovals($db, $INFO['id']);
        $review = $INPUT->has('approve') ? 'approve'
            : ($INPUT->has('ready_for_approval') ? 'ready_for_approval' : '');
        $view = new ApprovalDiff($INFO['id'], $approvals, $review, $this);
        $event->preventDefault();
        $view->show();
    }
}
