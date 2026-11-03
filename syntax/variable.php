<?php
/**
 * Variable syntax for approval plugin: Inserts version numbers and dates.
 * 
 * @license    GPL 2 (http://www.gnu.org/licenses/gpl.html)
 * @author     Ben van Magill <ben.vanmagill16@gmail.com>
 */
 
class syntax_plugin_approve_variable extends \dokuwiki\Extension\SyntaxPlugin  {
 
    public function getType() { return 'substition'; }
    public function getSort() { return 200; }
 
    public function connectTo($mode) {
        $this->Lexer->addSpecialPattern('@APPROVE_VERSION@',$mode,'plugin_approve_variable');
        $this->Lexer->addSpecialPattern('@APPROVE_DATE@',$mode,'plugin_approve_variable');
        $this->Lexer->addSpecialPattern('@APPROVE_USER@',$mode,'plugin_approve_variable');
    }
 
    public function handle($match, $state, $pos, Doku_Handler $handler) {
        switch ($match) {
            case '@APPROVE_VERSION@':
                $key = 'version';
                break;
            case '@APPROVE_DATE@':
                $key = 'approved';
                break;
            case '@APPROVE_USER@':
                $key = 'approved_by';
                break;
            default:
                $key = '';
                break;
        }
        return array($state, $match, $pos, $key);
    }
 
    public function render($mode, Doku_Renderer $renderer, $data) {
    // $data is what the function handle return.

        if($mode == 'xhtml'){
            /** @var Doku_Renderer_xhtml $renderer */
            global $INFO, $ACT;

            list($state, $match, $pos, $key) = $data;

            if (!$key) return;

            // Get the approvals db
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

            if (!$helper->use_approve_here($sqlite, $INFO['id'], $approver)) return;

            $approve = [];
            // Unsaved text is not the approved revision, even when previewing
            // an edit to a page whose saved revision is still approved.
            if (!empty($INFO['exists']) && !in_array($ACT, ['preview', 'changes', 'draft', 'recover'], true)) {
                $rev = !empty($INFO['rev']) ? $INFO['rev'] : @filemtime(wikiFN($INFO['id']));
                $res = $sqlite->query('SELECT approved, approved_by, version, minor_version
                                        FROM revision WHERE page=? AND rev=?', $INFO['id'], $rev);
                $approve = $sqlite->res_fetch_assoc($res);
            }

            $text = 'TBD';
            if (!empty($approve['approved'])) {
                switch ($key) {
                    case 'approved':
                        $date = strtotime($approve['approved']);
                        if ($date !== false) $text = dformat($date);
                        break;
                    case 'version':
                        if (isset($approve['version'], $approve['minor_version'])) {
                            $text = $approve['version'].'.'.$approve['minor_version'];
                        }
                        break;
                    default:
                        $text = $approve[$key] ?? 'TBD';
                        break;
                }
            }
            
            if (!$text) $text = 'TBD';

            $renderer->doc .= '<span class="approve-var" data-key="'.$key.'">'.hsc($text).'</span>';
            return true;
        }
        return false;
    }
}
