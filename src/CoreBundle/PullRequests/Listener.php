<?php

namespace CoreBundle\PullRequests;

use CoreBundle\Comments\CommentApiInterface;
use CoreBundle\Commits\RepositoryInterface as CommitRepositoryInterface;
use CoreBundle\PullRequests\RepositoryInterface as PullRequestRepositoryInterface;
use Lpdigital\Github\Entity\PullRequest;
use Lpdigital\Github\Entity\User;
use Psr\Log\LoggerInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class Listener
{
    const PRESTONBOT_NAME = 'prestonBot';
    const TABLE_ERROR = 'PR_TABLE_DESCRIPTION_ERROR';
    /**
     * @var CommentApiInterface
     */
    private $commentApi;
    /**
     * @var CommitRepositoryInterface
     */
    private $commitRepository;
    /**
     * @var LoggerInterface
     */
    private $logger;
    /**
     * @var ValidatorInterface
     */
    private $validator;
    /**
     * @var RepositoryInterface
     */
    private $repository;

    public function __construct(
        CommentApiInterface $commentApi,
        CommitRepositoryInterface $commitRepository,
        ValidatorInterface $validator,
        PullRequestRepositoryInterface $repository,
        LoggerInterface $logger
    ) {
        $this->commentApi = $commentApi;
        $this->commitRepository = $commitRepository;
        $this->logger = $logger;
        $this->validator = $validator;
        $this->repository = $repository;
    }

    /**
     * @param PullRequest $pullRequest
     */
    public function checkForTableDescription(PullRequest $pullRequest)
    {
        $bodyParser = new BodyParser($pullRequest->getBody());

        $validationErrors = $this->validator->validate($bodyParser);
        if (count($validationErrors) > 0) {
            $this->commentApi->sendWithTemplate(
                $pullRequest,
                '@Core/markdown/pr_table_errors.md.twig',
                ['errors' => $validationErrors]
            );

            $this->logger->info(sprintf('[Invalid Table] Pull request n° %s', $pullRequest->getNumber()));
        }
    }

    /**
     * @param PullRequest $pullRequest
     *
     * @return bool
     */
    public function removePullRequestValidationComment(PullRequest $pullRequest)
    {
        $bodyParser = new BodyParser($pullRequest->getBody());

        $bodyErrors = $this->validator->validate($bodyParser);
        if (0 === count($bodyErrors)) {
            $this->repository->removeCommentsIfExists(
                $pullRequest,
                self::TABLE_ERROR,
                self::PRESTONBOT_NAME
            );

            $this->logger->info(sprintf(
                '[Valid Table] Pull request (n° %s) table is now valid.',
                $pullRequest->getNumber()
            ));

            return true;
        }

        return false;
    }

    /**
     * @param PullRequest $pullRequest
     * @param User        $sender
     *
     * @return bool
     */
    public function welcomePeople(PullRequest $pullRequest, User $sender)
    {
        $userCommits = $this->commitRepository->findAllByUser($sender);

        if (0 === count($userCommits)) {
            $this->commentApi->sendWithTemplate(
                $pullRequest,
                '@Core/markdown/welcome.md.twig',
                ['username' => $sender->getLogin()]
            );

            $this->logger->info(sprintf(
                '[Contributor] `%s` was welcomed on Pull request n° %s',
                $pullRequest->getUser()->getLogin(),
                $pullRequest->getNumber()
            ));

            return true;
        }

        return false;
    }
}
