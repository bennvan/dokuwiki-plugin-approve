<?php

namespace dokuwiki\plugin\approve;

use dokuwiki\ChangeLog\PageChangeLog;

/** Shared history lookup and HTML for the diff view and page syntax. */
class ApprovalHistory
{
    private string $id;
    private array $approvals;
    private PageChangeLog $changelog;

    public function __construct(string $id, array $approvals, ?PageChangeLog $changelog = null)
    {
        $this->id = $id;
        $this->approvals = $approvals;
        $this->changelog = $changelog ?? new PageChangeLog($id);
    }

    public static function loadApprovals(\helper_plugin_sqlite $db, string $id): array
    {
        $rows = $db->res2arr($db->query('SELECT rev, version, minor_version, approved, approved_by, approval_message
            FROM revision WHERE page=? AND approved IS NOT NULL ORDER BY rev', $id));
        return array_column($rows, null, 'rev');
    }

    /** Resolve a version number or explicit rev:timestamp; never silently widen an invalid range. */
    public function resolveBoundary(string $value, int $default, int $ceiling): int
    {
        if ($value === '') return $default;
        if ($value === 'current') return $ceiling;
        if (preg_match('/^rev:([0-9]+)$/D', $value, $match)) return min((int) $match[1], $ceiling);
        if (preg_match('/^v?([0-9]+)\.([0-9]+)$/D', $value, $match)) {
            foreach ($this->approvals as $rev => $approval) {
                if ((int) $approval['version'] === (int) $match[1]
                    && (int) ($approval['minor_version'] ?? 0) === (int) $match[2]) {
                    return min((int) $rev, $ceiling);
                }
            }
        }
        throw new \InvalidArgumentException('Unknown approval history boundary');
    }

    /** Revisions in (from, to], newest first. Approval-only lists need no changelog scan. */
    public function entries(int $from, int $to, bool $includeEdits = true): array
    {
        $entries = [];
        if ($includeEdits && $to > $from) {
            $revs = [$to];
            $offset = 0;
            do {
                foreach ($revs as $rev) {
                    if ($rev <= $from) break 2;
                    if ($rev > $to) continue;
                    $info = $this->changelog->getRevisionInfo($rev);
                    if ($info) $entries[$rev] = ['rev' => $rev, 'edit' => $info];
                }
                $revs = $this->changelog->getRevisions($offset, 100);
                $offset += count($revs);
            } while ($revs);
        }
        // Keep approvals even if their edit-log entry has been pruned.
        foreach ($this->approvals as $rev => $approval) {
            if ($rev <= $from || $rev > $to) continue;
            $entries[$rev]['rev'] = $rev;
            $entries[$rev]['approval'] = $approval;
        }
        krsort($entries, SORT_NUMERIC);
        return $entries;
    }

    public function render(array $entries): string
    {
        $helper = plugin_load('helper', 'approve');
        $html = '<ul class="plugin__approve_history">';
        foreach ($entries as $entry) {
            $url = wl($this->id, ['rev' => $entry['rev']]);
            $html .= '<li>';
            if (isset($entry['edit'])) {
                $edit = $entry['edit'];
                $html .= '<a href="' . $url . '">' . hsc(dformat($entry['rev'])) . '</a> — '
                    . hsc(($edit['user'] ?? '') ?: $helper->getLang('review_anonymous')) . ': '
                    . hsc(trim($edit['sum'] ?? '') ?: $helper->getLang('review_no_summary'));
            }
            if (isset($entry['approval'])) {
                $approval = $entry['approval'];
                $version = ($approval['version'] ?? 0) . '.' . ($approval['minor_version'] ?? 0);
                $html .= '<div class="plugin__approve_history_approval"><strong><a href="' . $url . '">'
                    . hsc(sprintf($helper->getLang('review_approved'), $version)) . '</a></strong>';
                $date = strtotime($approval['approved'] ?? '');
                if ($date !== false) $html .= ' — ' . hsc(dformat($date));
                if (!empty($approval['approved_by'])) {
                    $html .= ' ' . hsc($helper->getLang('by')) . ' ' . hsc($approval['approved_by']);
                }
                $html .= $helper->formatApprovalMessage($approval['approval_message'] ?? null) . '</div>';
            }
            $html .= '</li>';
        }
        return $html . '</ul>';
    }
}
