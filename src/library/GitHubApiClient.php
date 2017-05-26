<?php
/**
 * Created by PhpStorm.
 * User: Bartek
 * Date: 2017-05-25
 * Time: 10:38
 */

namespace SchibstedApp;

use GuzzleHttp;

class GitHubApiClient
{
    const PULL_STATE_OPEN = 'open';
    const PULL_STATE_CLOSED = 'closed';
    const PULL_STATE_ALL = 'all';

    private $logger;

    public function __construct()
    {
        $root = realpath(dirname(__FILE__) . '/../');
        $this->logger = new \Monolog\Logger('GitHubApiClient');
        $file_handler = new \Monolog\Handler\StreamHandler($root."/logs/app.log");
        $this->logger->pushHandler($file_handler);
    }

    public function call($method,$resource)
    {
        $Client = new GuzzleHttp\Client();
        $options = array('headers' => [
            'Content-Type'     => 'application/json',
            ],
            'auth' => ['bartman4000', 'k00paa12']
        );

        try {
            $response = $Client->request($method, "https://api.github.com".$resource, $options);
        } catch (GuzzleHttp\Exception\ClientException $e) {
            $this->logger->addWarning("Called github resource {$resource} with response ".($e->getMessage()));
            throw new \Exception($e->getMessage(), $e->getCode(), $e);
        }

        $this->logger->addInfo("Called github resource {$resource} with response ".($response->getStatusCode()." ".$response->getReasonPhrase()));
        return $response->getBody()->getContents();
    }

    public function get($resource)
    {
        return $this->call("GET", $resource);
    }

    public function isRepo($owner, $repo)
    {
        try {
            $this->get("/repos/{$owner}/{$repo}");
            return true;
        } catch (\Exception $e)
        {
            if($e->getCode() == 404)
            {
                return false;
            }
            else{
                throw new \Exception($e->getMessage(), $e->getCode(), $e);
            }
        }
    }

    public function getStarsCount($owner, $repo)
    {
        $content = $this->get("/repos/{$owner}/{$repo}/stargazers");
        $stargazers = json_decode($content);
        return count($stargazers);
    }

    public function getWatchersCount($owner, $repo)
    {
        $content = $this->get("/repos/{$owner}/{$repo}/subscribers");
        $watchers = json_decode($content);
        return count($watchers);
    }

    public function getForksCount($owner, $repo)
    {
        $content = $this->get("/repos/{$owner}/{$repo}/forks");
        $forks = json_decode($content);
        return count($forks);
    }

    public function getPullsCount($owner, $repo, $state = self::PULL_STATE_OPEN)
    {
        $content = $this->get("/repos/{$owner}/{$repo}/pulls?state=".$state);
        $pulls = json_decode($content);
        return count($pulls);
    }

    public function getLatestReleaseDate($owner, $repo)
    {
        try {
            $content = $this->get("/repos/{$owner}/{$repo}/releases/latest");
            $content = json_decode($content);
        } catch (\Exception $e)
        {
            $this->logger->addWarning($e->getMessage());
            if($e->getCode() == 404)
            {
                return null;
            }
            else{
                throw new \Exception($e->getMessage(), $e->getCode(), $e);
            }
        }
        $date = new \DateTime($content->published_at);
        return $date->format("Y-m-d H:i:s");
    }

    public function getLastMergeDate($owner, $repo)
    {
        $mergedPulls = $this->getMergedPulls($owner, $repo);

        $sortedPullsAsc = $this->sortPullsByMergedTime($mergedPulls);

        $mergeTimes = array();
        foreach($sortedPullsAsc as $pull)
        {
            $mergeTimes[] = $pull->merged_at;
        }
        $mergeTime = array_pop($mergeTimes);
        $this->logger->addDebug("mergeTime: {$mergeTime}");
        $date = new \DateTime($mergeTime);
        return $date->format("Y-m-d H:i:s");
    }

    public function getMergedPulls($owner, $repo)
    {
        $content = $this->get("/repos/{$owner}/{$repo}/pulls?state=all");
        $pulls = json_decode($content);
        $mergedPulls = array_filter($pulls, function($pull) {
            return isset($pull->merged_at) && !empty($pull->merged_at);
        });
        return $mergedPulls;
    }

    public function sortPullsByMergedTime($mergedPulls, $direction = "ASC")
    {
        usort($mergedPulls, function ($a,$b) use ($direction)
        {
            $d1 = new \DateTime($a->merged_at);
            $t1 = $d1->getTimeStamp();
            $d2 = new \DateTime($b->merged_at);
            $t2 = $d2->getTimeStamp();
            if($direction == "DESC") {
                return $t1 < $t2;
            } else{
                return $t1 > $t2;
            }
        });

        return $mergedPulls;
    }

    public function getUpdateDate($owner, $repo)
    {
        $content = $this->get("/repos/{$owner}/{$repo}");
        $content = json_decode($content);
        $date = new \DateTime($content->updated_at);
        return $date->format("Y-m-d H:i:s");
    }
}