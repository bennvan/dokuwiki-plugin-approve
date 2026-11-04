<?php

namespace dokuwiki\plugin\approve;

use dokuwiki\ChangeLog\RevisionInfo;

/** Add the approval message below the core edit summary in a diff heading. */
class ApprovalRevisionInfo extends RevisionInfo
{
    private string $messageHtml;

    public function __construct(RevisionInfo $revision, string $messageHtml)
    {
        $this->info = $revision->info;
        $this->messageHtml = $messageHtml;
    }

    public function showEditor()
    {
        return parent::showEditor() . $this->messageHtml;
    }
}
