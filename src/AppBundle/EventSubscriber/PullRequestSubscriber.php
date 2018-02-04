<?php

namespace AppBundle\EventSubscriber;

use AppBundle\Diff\Diff;
use AppBundle\Event\GitHubEvent;
use AppBundle\Issues\Listener as IssuesListener;
use AppBundle\PullRequests\Listener as PullRequestsListener;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

class PullRequestSubscriber implements EventSubscriberInterface
{
    const TRANS_PATTERN = '#(trans\(|->l\()#';
    const CLASSIC_PATH = '#^themes\/classic\/#';

    /**
     * @var IssuesListener
     */
    private $issuesListener;

    /**
     * @var PullRequestsListener
     */
    private $pullRequestsListener;

    /**
     * @var bool
     */
    private $enableOnPrCreation;

    public function __construct(
        IssuesListener $issuesListener,
        PullRequestsListener $pullRequestsListener,
        bool $enableOnPrCreation)
    {
        $this->issuesListener = $issuesListener;
        $this->pullRequestsListener = $pullRequestsListener;
        $this->enableOnPrCreation = $enableOnPrCreation;
    }

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            'pullrequestevent_opened' => [
                ['checkForTableDescription', 254],
                ['welcomePeople', 255],
                ['checkForNewTranslations', 252],
                ['initLabels', 254],
                ['checkCommits', 252],
                ['checkForClassicChanges', 252],
                ['checkIfPrFixCriticalIssue', 253],
            ],
            'pullrequestevent_edited' => [
               ['removePullRequestValidationComment', 255],
               ['removeCommitValidationComment', 255],
               ['checkForNewTranslations', 252],
               ['checkForClassicChanges', 252],
            ],
            'pullrequestevent_synchronize' => [
                ['removeCommitValidationComment', 255],
            ],
        ];
    }

    /**
     * @param GitHubEvent $githubEvent
     *
     * For now, only add "Needs Review" label
     */
    public function initLabels(GitHubEvent $githubEvent)
    {
        $pullRequest = $githubEvent->getEvent()->pullRequest;

        if (true === $this->enableOnPrCreation) {
            $this->issuesListener->handlePullRequestCreatedEvent($pullRequest->getNumber());

            $githubEvent->addStatus([
                'event' => 'pr_opened',
                'action' => 'labels initialized',
                ])
            ;
        }
    }

    /**
     * @param GitHubEvent $githubEvent
     *
     * This event MUST be spawned second
     */
    public function checkForTableDescription(GitHubEvent $githubEvent)
    {
        $pullRequest = $githubEvent->getEvent()->pullRequest;

        $this->pullRequestsListener->checkForTableDescription($pullRequest);

        $githubEvent->addStatus([
            'event' => 'pr_opened',
            'action' => 'table description checked',
            ])
        ;
    }

    /**
     * @param GitHubEvent $githubEvent
     *
     * Validate the commits labels
     */
    public function checkCommits(GitHubEvent $githubEvent)
    {
        $pullRequest = $githubEvent->getEvent()->pullRequest;

        $hasErrors = $this->pullRequestsListener->checkCommits($pullRequest);

        $githubEvent->addStatus([
            'event' => 'pr_opened',
            'action' => 'commits labels checked',
            'status' => $hasErrors ? 'not_valid' : 'valid',
        ]);
    }

    /**
     * @param GitHubEvent $githubEvent
     *
     * If a call to trans or l function is done, add
     * "waiting for wording" label
     */
    public function checkForNewTranslations(GitHubEvent $githubEvent)
    {
        $event = $githubEvent->getEvent();
        $pullRequest = $event->pullRequest;
        $diff = Diff::create(file_get_contents($pullRequest->getDiffUrl()));

        if ($found = $diff->additions()->contains(self::TRANS_PATTERN)->match()) {
            $this->issuesListener->handleWaitingForWordingEvent($pullRequest->getNumber());
        }

        $eventStatus = 'opened' === $event->getAction() ? 'opened' : 'edited';

        $githubEvent->addStatus([
            'event' => 'pr_'.$eventStatus,
            'action' => 'checked for new translations',
            'status' => $found ? 'found' : 'not_found',
            ])
        ;
    }

    public function checkIfPrFixCriticalIssue(GitHubEvent $githubEvent)
    {
        $labelWasAdded = $this->issuesListener
            ->addLabelCriticalLabelIfNeeded($githubEvent->getEvent()->pullRequest)
        ;

        if ($labelWasAdded) {
            $githubEvent->addStatus([
                'event' => 'pr_created',
                'action' => 'critical label was added',
            ]);
        }
    }

    /**
     * @param GitHubEvent $githubEvent
     *
     * If a change occurs in one of classic's files, add
     * "report on StarterTheme" label
     */
    public function checkForClassicChanges(GitHubEvent $githubEvent)
    {
        $event = $githubEvent->getEvent();
        $pullRequest = $event->pullRequest;
        $diff = Diff::create(file_get_contents($pullRequest->getDiffUrl()));

        if ($found = $diff->path(self::CLASSIC_PATH)->match()) {
            $this->issuesListener->handleClassicChangesEvent($pullRequest->getNumber());
        }

        $eventStatus = 'opened' === $event->getAction() ? 'opened' : 'edited';

        $githubEvent->addStatus([
            'event' => 'pr_'.$eventStatus,
            'action' => 'checked for changes on Classic Theme',
            'status' => $found ? 'found' : 'not_found',
        ])
        ;
    }

    /**
     * @param GitHubEvent $githubEvent
     *
     * This event MUST be spawned second.
     * Send a comment to welcome very first contribution.
     */
    public function welcomePeople(GitHubEvent $githubEvent)
    {
        $pullRequest = $githubEvent->getEvent()->pullRequest;
        $sender = $githubEvent->getEvent()->sender;

        $this->pullRequestsListener->welcomePeople($pullRequest, $sender);

        $githubEvent->addStatus([
            'event' => 'pr_opened',
            'action' => 'user welcomed',
            ])
        ;
    }

    /**
     * @param GitHubEvent $githubEvent
     *
     * If description become valid, the comment should be removed
     */
    public function removePullRequestValidationComment(GithubEvent $githubEvent)
    {
        $pullRequest = $githubEvent->getEvent()->pullRequest;

        if ($pullRequest->isClosed() || $pullRequest->isMerged()) {
            return;
        }

        $success = $this->pullRequestsListener->removePullRequestValidationComment($pullRequest);

        if ($success) {
            $githubEvent->addStatus([
                'event' => 'pr_edited',
                'action' => 'preston validation comment removed',
                ])
            ;
        }
    }

    /**
     * @param GitHubEvent $githubEvent
     *
     * If commits labels become valid, the comment should be removed
     */
    public function removeCommitValidationComment(GithubEvent $githubEvent)
    {
        $pullRequest = $githubEvent->getEvent()->pullRequest;

        if ($pullRequest->isClosed() || $pullRequest->isMerged()) {
            return;
        }

        $success = $this->pullRequestsListener->removeCommitValidationComment($pullRequest);

        if ($success) {
            $githubEvent->addStatus([
                'event' => 'pr_edited',
                'action' => 'preston validation commit comment removed',
                ])
            ;
        }
    }
}
