<?php

namespace dokuwiki\plugin\approve;

use dokuwiki\ChangeLog\RevisionInfo;
use dokuwiki\Ui\PageDiff;

/** Keep core diff rendering and navigation, adding approval labels and edit summaries. */
class ApprovalDiff extends PageDiff
{
    private array $approvals;
    private string $review;
    private $plugin;

    public function __construct($id, array $approvals, string $review, $plugin)
    {
        parent::__construct($id);
        $this->approvals = $approvals;
        $this->review = $review;
        $this->plugin = $plugin;
    }

    protected function handle()
    {
        global $INPUT;
        parent::handle();
        // Initial review always covers the entire interval since the last approval.
        // Explicit dropdown selections are respected when exploring that history.
        if ($this->review !== '' && !$INPUT->has('rev2')) {
            $this->rev2 = $this->changelog->currentRevision();
            $older = array_filter(array_keys($this->approvals), fn($rev) => $rev < $this->rev2);
            $this->rev1 = $older ? max($older) : 0;
        }
    }

    protected function preProcess()
    {
        if ($this->review === '' || $this->rev1 !== 0) {
            parent::preProcess();
        } else {
            // First approval: compare an empty document with the selected revision.
            $this->RevInfo1 = new RevisionInfo();
            $this->RevInfo1->append(['text' => '']);
            $this->RevInfo2 = new RevisionInfo($this->changelog->getRevisionInfo($this->rev2));
            $current = $this->changelog->isCurrentRevision($this->rev2);
            $this->RevInfo2->isCurrent($current);
            $this->RevInfo2->append(['text' => rawWiki($this->id, $current ? '' : $this->rev2)]);
        }
        $helper = plugin_load('helper', 'approve');
        $this->RevInfo1 = new ApprovalRevisionInfo($this->RevInfo1,
            $helper->formatApprovalMessage($this->approvals[$this->rev1]['approval_message'] ?? null));
        $this->RevInfo2 = new ApprovalRevisionInfo($this->RevInfo2,
            $helper->formatApprovalMessage($this->approvals[$this->rev2]['approval_message'] ?? null));
    }

    private function revisionLabel($rev): string
    {
        if (!$rev) return $this->plugin->getLang('review_empty');
        if (isset($this->approvals[$rev])) {
            $row = $this->approvals[$rev];
            return sprintf($this->plugin->getLang('review_approved'), $row['version'] . '.' . ($row['minor_version'] ?? 0));
        }
        return $this->plugin->getLang($this->changelog->isCurrentRevision($rev) ? 'review_current' : 'draft');
    }

    protected function buildRevisionOptions($side, $revs)
    {
        $options = parent::buildRevisionOptions($side, $revs);
        foreach ($options as $rev => &$option) {
            $option['label'] = '[' . $this->revisionLabel($rev) . '] ' . $option['label'];
        }
        unset($option);
        if ($side === 'older' && !$this->rev1) {
            $options = [0 => ['label' => $this->revisionLabel(0)]] + $options;
        }
        return $options;
    }

    /** Add a hidden review flag to core forms without copying their implementation. */
    private function preserveReview(string $html): string
    {
        if ($this->review === '') return $html;
        return str_replace('</form>', '<input type="hidden" name="' . $this->review . '" value="0" /></form>', $html);
    }

    protected function buildDropdownSelector($side, $options)
    {
        return $this->preserveReview(parent::buildDropdownSelector($side, $options));
    }

    protected function diffViewlink($linktype, $rev1, $rev2 = null)
    {
        $html = parent::diffViewlink($linktype, $rev1, $rev2);
        if ($this->review === '') return $html;
        return preg_replace('/(href="[^"]*)"/', '$1&amp;' . $this->review . '=0"', $html);
    }

    protected function showDiffViewSelector()
    {
        ob_start();
        parent::showDiffViewSelector();
        echo $this->preserveReview(ob_get_clean());
    }

    public function show()
    {
        parent::show();
        $this->showEditSummaries();
    }

    private function showEditSummaries(): void
    {
        $history = new ApprovalHistory($this->id, $this->approvals, $this->changelog);
        $entries = $history->entries((int) $this->rev1, (int) $this->rev2);
        echo '<details class="plugin__approve_edits" open><summary>',
            hsc(sprintf($this->plugin->getLang('review_edits'), count($entries))), '</summary>',
            $history->render($entries), '</details>';
    }
}
