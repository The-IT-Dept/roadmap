<?php

namespace App\Services;

use Throwable;
use Github\Client;
use Github\AuthMethod;
use Github\ResultPager;
use Illuminate\Support\Collection;
use GrahamCampbell\GitHub\GitHubManager;
use GrahamCampbell\GitHub\Facades\GitHub;

class GitHubService
{

    public function getClient(): Client
    {
        /** @var GitHubManager $client */
        $client = GitHub::connection('private');

        $token = cache()->remember('github.token', now()->addMinutes(7), function () use ($client) {

            $installationId = data_get($client->apps()->findInstallations(), '0.id');

            return $client->apps()->createInstallationToken($installationId);
        });

        $client->authenticate(
            $token['token'],
            authMethod: AuthMethod::ACCESS_TOKEN
        );

        return $client;
    }

    public function getRepositories(?string $searchQuery = null): Collection
    {
        if (!$this->isEnabled()) {
            return collect();
        }
        $gitHubClient = $this->getClient();


        try {
            $gitHubClient = $this->getClient();

            return collect($gitHubClient->apps()->listRepositories()['repositories'])
                ->filter(fn($repo) => str_contains($repo['full_name'], $searchQuery))
                ->mapWithKeys(fn($repo) => [$repo['full_name'] => $repo['full_name']]);

        } catch (Throwable $e) {
            logger()->error("Failed to retrieve GitHub repo's: {$e->getMessage()}");

            return collect();
        }
    }

    public function isEnabled(): bool
    {
        return config('github.enabled');
    }

    public function getIssuesForRepository(?string $repository, ?string $searchQuery = null): Collection
    {
        if (!$this->isEnabled() || $repository === null) {
            return collect();
        }

        $repo = str($repository)->explode('/');

        try {
            $gitHubClient = $this->getClient();

            $paginator = new ResultPager($gitHubClient);

            return collect($paginator->fetchAll($gitHubClient->api('issues'), 'all', [$repo[0], $repo[1]]))
                ->filter(fn($issue) => str_contains('#' . $issue['number'] . ' - ' . $issue['title'], $searchQuery))
                ->filter(fn($issue) => !isset($issue['pull_request']))
                ->mapWithKeys(fn($issue) => [$issue['number'] => '#' . $issue['number'] . ' - ' . $issue['title']]);

        } catch (Throwable $e) {
            logger()->error("Failed to retrieve GitHub repo's: {$e->getMessage()}");

            return collect();
        }
    }

    public function getIssueTitle(?string $repository, ?int $issueNumber): ?string
    {
        if (!$this->isEnabled() || $repository === null || $issueNumber === null) {
            return null;
        }

        $repo = str($repository)->explode('/');

        try {
            $issue = $this->getClient()->issues()->show($repo[0], $repo[1], $issueNumber);

            return "#{$issue['number']} - {$issue['title']}";
        } catch (Throwable $e) {
            logger()->error("Failed to retrieve GitHub issue #{$issueNumber}: {$e->getMessage()}");

            return null;
        }
    }

    public function createIssueInRepository(string $repository, $title, $body): int
    {
        $repo = str($repository)->explode('/');


        return $this->getClient()->issues()->create($repo[0], $repo[1], [
            'title' => $title,
            'body' => $body,
        ])['number'];
    }
}
