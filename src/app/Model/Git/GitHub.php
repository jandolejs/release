<?php


namespace App\Model\Git;


use App\Configuration;
use App\Exceptions\BranchException;
use App\Exceptions\GitApiCallException;
use App\Exceptions\GitHubException;
use App\Model\Task;
use Exception;
use Github\Client;
use Nette\Http\Url;
use stdClass;

class GitHub
{

    private static Client $git_client;

    private static string $token;
    public static string $company;
    public static string $repository;

    const PULL_STATE_ALL = 'all';
    const PULL_STATE_OPEN = 'open';
    const PULL_STATE_CLOSED = 'closed';

    public function __construct(string $token) {
        self::$token = $token;
    }

    /**
     * @noinspection PhpUnused
     */
    public function isPullReady(stdClass $_pull): bool|null
    {
        $ready = false;

        if (isset($_pull->labels)) {

            // Check if it has label for ready
            foreach ($_pull->labels as $label) {
                if ($label->name === Configuration::get('pull/label/ready'))
                    $ready = true;
            }

            // Check if it has no blocking labels
            foreach ($_pull->labels as $label) {
                if ($label->name === Configuration::get('pull/label/prevent'))
                    $ready = null;
            }
        }
        return $ready;
    }

    /**
     * @throws \App\Exceptions\GitHubException
     */
    public function getPulls(): array
    {
        $review  = "review:approved";
        $status  = "is:open";
        $repo    = "repo:" . self::$company . "/" . Configuration::get('github/repository');
        $q       = "is:pr";
        $q       = $q . "+" . $status . "+" . $review . "+" . $repo;

        $pulls    = array();
        try {
            $response = $this->call("/search/issues?q=$q&sort:updated-desc");
        } catch (Exception $e) {
            throw new GitHubException($e->getMessage());
        }

        if (!$response) throw new GitHubException("Loading pulls failed");

        foreach ($response->items as $pull) {
            $pull->custom_labels = $this->parseLabels($pull);
            $pull->is_ready = $this->isPullReady($pull);
            $pull->custom_title = new Task\Title($pull->title);
            $pulls[$pull->number] = $pull;
        }

        return $pulls;
    }

    public function parseLabels(stdClass $pull): array
    {
        $result = array();

        // Get GitHub labels
        foreach ($pull->labels as $label) {

            $editedLabel = [
                'name' => $label->name,
                'color'=> $label->color,
                'description' => $label->description,
            ];

            $result[] = $editedLabel;
        }

        return $result;
    }

    /**
     * @throws Exception
     */
    public function call(string $path = '', string $type = 'GET', array $data = []): mixed
    {
        // Prepare path
        if (!str_starts_with($path, "https://")) {
            if (!preg_match("~^\/.+~", $path)) {
                $path = "/repos/" . self::$company . "/" . Configuration::get('github/repository') . "/$path";
            }
            $path = Configuration::get('github/api') . "$path";
            $path = str_replace("+", "%20", $path);
        }

        if ($type === 'GET' && !empty($data) && !strpos($path, "/?")) {
            $path = $path . "?" . http_build_query($data);
        }

        $ch = curl_init();
        curl_setopt_array($ch, [
            CURLOPT_POST           => FALSE,
            CURLOPT_URL            => $path,
            CURLOPT_USERPWD        => self::$token,
            CURLOPT_CUSTOMREQUEST  => $type,
            CURLOPT_POSTFIELDS     => json_encode($data),
            CURLOPT_HTTPHEADER     => array('Content-type: application/json'),
            CURLOPT_RETURNTRANSFER => TRUE,
            CURLOPT_USERAGENT      => 'Mozilla/5.0 (Macintosh; Intel Mac OS X)',
        ]);

        $result = json_decode(curl_exec($ch));
        curl_close($ch);

        $info = curl_getinfo($ch);
        if ($info['http_code'] === 204) return true; // empty response

        if (empty($result)) throw new Exception("Github error - api call failed");

        if (isset($result->message)) {
            throw new GitApiCallException($result->message, $result, $info);
        }

        return $result;
    }

    /**
     * Call github API for creating branch and check result
     * @param string $name Name of branch we want to create
     * @return \App\Model\Git\Branch Branch we just created
     * @throws Exception If anything fail, throw Exception
     */
    public function createBranch(string $name): Branch
    {
        // Call github API for creating branches
        $result = $this->call("git/refs", "POST", [
            'ref' => "refs/heads/$name",
            'sha' => $this->getMasterSha(),
        ]);

        // Check result of API call
        if (!empty($result->message)) throw new BranchException($result->message);

        // Return newly created Branch
        return new Branch($result->ref, $result->object->sha);
    }

    /**
     * @throws Exception
     */
    public function getMasterSha(): string
    {
        $result = $this->call("git/refs/heads/" . Configuration::get('github/master'));

        if (!$result) throw new Exception("Github request failed");
        $sha = $result->object->sha;
        if (empty($sha)) {
            throw new Exception("Could not find SHA of branch '" . Configuration::get('github/master') . "'");
        }

        return $sha;
    }

    /**
     * Checks if client exists
     *     - if so then returns it
     *     - if not, creates new and returns it
     */
    public static function getClient(): ?Client
    {

        // check if new client is needed
        if (!isset(self::$git_client)) {
            $client = new Client();
            $client->authenticate(self::$token, NULL, Client::AUTH_ACCESS_TOKEN);
            self::$git_client = $client;
        }

        return self::$git_client; // return authenticated Github Client
    }

    /**
     * @throws Exception
     */
    public function deleteBranch(Branch $branch)
    {
        try {
            $this->call("git/refs/heads/$branch", "DELETE");
        } catch (Exception $e) {
            if ($e->getMessage() == "Reference does not exist") {
                return;
            }
            throw $e;
        }
    }

    /**
     * @throws Exception
     */
    public function mergeTask(Task $task, Branch $releaseBranch): string
    {
        $head   = new Branch($task->getBranch(), $task->getSha());
        $base   = $releaseBranch;
        $result = $this->merge($base, $head);

        if (empty($result->sha)) throw new Exception("Merging task into release failed");
        return $result->sha;
    }

    /**
     * @throws Exception
     */
    public function merge(Branch $base, Branch $head)
    {
        $result = $this->call("merges", "POST", [
            'base' => "$base", 'head' => "$head",
            'commit_message' => "Merge '$head' into '$base'"
        ]);

        if (!empty($result->message))
            throw new Exception($result->message);

        return $result;
    }

    /**
     * @throws Exception
     */
    public function setReady(int $pull)
    {
        $this->call("issues/$pull/labels", 'POST', array('labels' => [Configuration::get('pull/label/ready')]));
        try {
            $this->call("issues/$pull/labels/" . rawurlencode(Configuration::get('pull/label/waits')), 'DELETE');
        } /** @noinspection PhpUnusedLocalVariableInspection */ catch (Exception $e) {
        }
    }

    /**
     * @throws Exception
     */
    public function addManualLabel(int $pull)
    {
        $this->call("issues/$pull/labels", 'POST', array('labels' => [Configuration::get('pull/label/manual')]));
    }

    public function removeReady(int $pull)
    {
        try {
            $this->call("issues/$pull/labels/" . rawurlencode(Configuration::get('pull/label/waits')), 'DELETE');
        } /** @noinspection PhpUnusedLocalVariableInspection */ catch (Exception $e) {
        }
        try {
            $this->call("issues/$pull/labels/" . rawurlencode(Configuration::get('pull/label/ready')), 'DELETE');
        } /** @noinspection PhpUnusedLocalVariableInspection */ catch (Exception $e) {
        }
    }

    public static function getPullLink(int $pull): string
    {
        return Configuration::get('github/url') . "/" . self::$company . "/" . Configuration::get('github/repository') . "/pull/$pull";
    }

    public function getDiffLink(Branch $branch): string
    {
        return Configuration::get('github/url') . "/" . self::$company . "/" . Configuration::get('github/repository') . "/compare/$branch";
    }

    /**
     * @throws Exception
     */
    public function getApiRate(): array
    {
        return json_decode(json_encode($this->call('/rate_limit')), true);
    }

    /**
     * Get pull requests by date from/to
     * @throws \Exception
     * @var bool $refetch Fetch new data, instead of using last fetched ones
     */
    public function getPullArray($state, $from, $to): array
    {
        if (empty($_SESSION['github']['ti']))
        {
            $pulls = array();
            $all = false;
            $perPage = 20;
            $tries = 1;

            while (!$all && $tries++ <= 5) {

                $review  = "review:approved";
                $status  = "is:" . $state;
                $repo    = "repo:" . self::$company . "/" . Configuration::get('github/repository');
                $q       = "merged:$from..$to";
                $q       = $q . "+" . $status . "+" . $review . "+" . $repo;

                $fetched = $this->call("/search/issues?q=$q&sort:updated-desc");
                $fetched = $fetched->items;

                foreach ($fetched as $pull) {
                    $pulls[$pull->number] = $pull;
                }
                $all = sizeof($fetched) < $perPage;
            }

            $_SESSION['github']['ti'] = $pulls;
        }

        return $_SESSION['github']['ti'];
    }

}
