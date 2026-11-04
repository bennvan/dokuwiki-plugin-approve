<?php

use dokuwiki\Extension\SyntaxPlugin;
use dokuwiki\plugin\approve\ApprovalHistory;

/** Embed this page's edit/approval history, bounded by the revision being viewed. */
class syntax_plugin_approve_history extends SyntaxPlugin
{
    public function getType() { return 'substition'; }
    public function getPType() { return 'block'; }
    public function getSort() { return 20; }

    public function connectTo($mode)
    {
        $this->Lexer->addSpecialPattern('----+ *approve history *-+\n.*?----+', $mode, 'plugin_approve_history');
    }

    public function handle($match, $state, $pos, Doku_Handler $handler)
    {
        $params = ['from' => '', 'to' => '', 'show' => 'all', 'order' => 'newest'];
        $lines = explode("\n", $match);
        array_shift($lines);
        array_pop($lines);
        foreach ($lines as $line) {
            if (trim($line) === '') continue;
            $pair = explode(':', $line, 2);
            $key = trim($pair[0]);
            if (count($pair) !== 2 || !array_key_exists($key, $params)) return ['error' => true];
            $params[$key] = trim($pair[1]);
        }
        if (!in_array($params['show'], ['all', 'approvals'], true)
            || !in_array($params['order'], ['newest', 'oldest'], true)) return ['error' => true];
        return $params;
    }

    public function render($mode, Doku_Renderer $renderer, $data)
    {
        global $ID, $INFO;
        if ($mode === 'metadata') {
            $renderer->meta['plugin']['approve']['history'] = true;
            return true;
        }
        if ($mode !== 'xhtml') return false;
        // History depends on approvals and the viewer's access to drafts. Do not
        // reuse another user's rendered history or cache an approval change away.
        $renderer->info['cache'] = false;
        if (!is_array($data) || isset($data['error'])) {
            $renderer->doc .= '<p>' . hsc($this->getLang('history_invalid')) . '</p>';
            return true;
        }
        $id = $ID;
        if (auth_quickaclcheck($id) < AUTH_READ) return true;
        try {
            $db = plugin_load('helper', 'approve_db')->getDB();
            $helper = plugin_load('helper', 'approve');
            if (!$helper->use_approve_here($db, $id, $approver)) return true;
            $approvals = ApprovalHistory::loadApprovals($db, $id);
            $ceiling = (int) @filemtime(wikiFN($id));
            if (($INFO['id'] ?? '') === $id && !empty($INFO['rev'])) $ceiling = (int) $INFO['rev'];
            if (!$helper->client_can_see_drafts($id, $approver)
                && !$helper->client_can_approve($id, $approver)) {
                if (!$approvals) return true;
                $ceiling = min($ceiling, max(array_keys($approvals)));
            }
            $history = new ApprovalHistory($id, $approvals);
            $from = $history->resolveBoundary($data['from'], 0, $ceiling);
            $to = $history->resolveBoundary($data['to'], $ceiling, $ceiling);
            if ($from > $to) throw new InvalidArgumentException('Reversed approval history range');
            $entries = $history->entries($from, $to, $data['show'] === 'all');
            if ($data['order'] === 'oldest') $entries = array_reverse($entries, true);
            $renderer->doc .= $history->render($entries);
        } catch (InvalidArgumentException $e) {
            $renderer->doc .= '<p>' . hsc($this->getLang('history_invalid')) . '</p>';
        } catch (Exception $e) {
            msg($e->getMessage(), -1);
        }
        return true;
    }
}
