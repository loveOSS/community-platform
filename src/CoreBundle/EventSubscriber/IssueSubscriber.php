<?php

namespace CoreBundle\EventSubscriber;

use CoreBundle\Event\GitHubEvent;
use CoreBundle\Issues\Listener;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class IssueSubscriber implements EventSubscriberInterface
{
    /**
     * @var Listener
     */
    private $issuesListener;

    /**
     * @var bool
     */
    private $enableLabels;

    public function __construct(Listener $issuesListener, bool $enableLabels)
    {
        $this->issuesListener = $issuesListener;
        $this->enableLabels = $enableLabels;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
           'issuesevent_labeled' => [
               ['updateLabels', 255],
           ],
        ];
    }

    /**
     * Changes "Bug" issues to "Needs Review".
     */
    public function updateLabels(GitHubEvent $githubEvent)
    {
        if (true === $this->enableLabels) {
            $event = $githubEvent->getEvent();

            $status = $this->issuesListener
                ->handleLabelAddedEvent(
                    $event->issue->getNumber(),
                    $event->label->getName()
            );

            $action = (null === $status) ? 'ignored' : 'added required labels';
            $githubEvent->addStatus([
                'event' => 'issue_event_labeled',
                'action' => $action,
            ]);
        }
    }
}
