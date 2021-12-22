<?php

namespace App;


use App\Model\Git\GitHub;
use App\Model\Git\PullRequest;
use App\Model\Release;
use Exception;
use JetBrains\PhpStorm\Pure;

class PullFactory
{
    private GitHub $gitHub;

    public function __construct(GitHub $gitHub)
    {
        $this->gitHub = $gitHub;
    }

    /**
     * @throws \Exception
     */
    #[Pure] public function load(int $id): PullRequest
    {
        return new PullRequest($this->gitHub, $id);
    }

    /**
     * @throws \Exception
     */
    public function create(Release $release): int
    {
        $branchName = $release->getBranch();
        $releaseId  = $release->getId();
        $tasks      = $release->getTasks();

        if (!$branchName) throw new Exception("Release $releaseId has no branch");
        if (empty($tasks)) throw new Exception("Release $releaseId has no tasks");

        $data = [
            'base'  => Configuration::get("github/master"),
            'head'  => (string)$release->getBranch(),
            'title' => $release->getName(),
            'body'  => $release->getBody(),
        ];

        $result = GitHub::getClient()
            ->pullRequest()
            ->create(Configuration::get("github/company"), Configuration::get("github/repository"), $data);

        // Check GitHub response
        if (!empty($result['message'])) { // Message means error
            throw new Exception($result['message']);
        }
        if (empty($result['html_url'])) { // If PR already exists, throw exception
            throw new Exception("PR for {$release->getBranch()} already exists.");
        }

        return $result['number'];
    }

    /**
     * @param int $id
     * @return array
     */
    public function getFilenames(int $id): array
    {
        $page      = 0;
        $filenames = array();

        do {
            $files = $this->gitHub->getClient()
                ->pullRequest()
                ->files(Configuration::get("github/company"), Configuration::get("github/repository"), $id, ['page' => $page++]);

            foreach ($files as $file) {
                $filenames[] = $file['filename'];
            }

        } while (sizeof($files) >= 30);

        return $filenames;
    }


}
