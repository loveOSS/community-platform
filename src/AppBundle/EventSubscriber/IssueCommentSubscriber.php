<?php

namespace AppBundle\EventSubscriber;

use AppBundle\Event\GitHubEvent;
use AppBundle\Issues\Listener;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class IssueCommentSubscriber implements EventSubscriberInterface
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
           'issuecommentevent_created' => [
               ['addLabels', 255],
           ],
        ];
    }

    /**
     * @param GitHubEvent $githubEvent
     */
    public function addLabels(GitHubEvent $githubEvent)
    {
        if (true === $this->enableLabels) {
            $event = $githubEvent->getEvent();

            $this->issuesListener
                ->handleCommentAddedEvent(
                    $event->issue->getNumber(),
                    $event->comment->getBody()
            );

            $githubEvent->addStatus([
                'event' => 'issue_comment_created',
                'action' => 'add labels if required',
            ]);
        }
    }
}
