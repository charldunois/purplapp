<?php namespace Purplapp\Adn;

use GuzzleHttp\Client as GuzzleClient;
use Psr\Log\LoggerInterface;

class Client
{
    public $accessTokenUrl = "https://account.app.net/oauth/access_token";

    public $userResourceUrl = "https://api.app.net/users";

    public $postResourceUrl = "https://api.app.net/posts";

    public $channelResourceUrl = "https://api.app.net/channels";

    /**
     * @var Cache
     */
    protected $cache = [];

    /**
     * @var GuzzleClient
     */
    protected $client;

    /**
     * @var string
     */
    private $id;

    /**
     * @var string
     */
    private $secret;

    /**
     * @var string
     */
    private $redirect;

    /**
     * @var LoggerInterface
     */
    private $logger;

    /**
     * @var string
     */
    private $accessToken;

    /**
     * Constructs a new Client object
     *
     * @param string          $id           The client ID to use
     * @param string          $secret       The client secret to use
     * @param string          $redirect     The redirect URL to use
     * @param LoggerInterface $logger       A logger to log things with
     * @param GuzzleClient    $client       A Guzzle Client to make requests with
     * @param string          $accessToken  The access token to use for auth'd requests
     */
    public function __construct(
        $id,
        $secret,
        $redirect,
        LoggerInterface $logger,
        GuzzleClient $client = null,
        $accessToken
    ) {
        $this->id          = $id;
        $this->secret      = $secret;
        $this->redirect    = $redirect;
        $this->logger      = $logger;
        $this->client      = $client;
        $this->accessToken = $accessToken;

        $this->cache = new Cache();
    }

    public function getAccessToken($code)
    {
        $resp = AccessTokenResponse::wrap($this->client->post($this->accessTokenUrl, [
            "body" => [
                "client_id"     => $this->id,
                "client_secret" => $this->secret,
                "redirect_uri"  => $this->redirect,
                "grant_type"    => "authorization_code",
                "code"          => $code,
            ],
        ]));

        $this->accessToken = $resp->access_token;

        return $resp;
    }

    public function getUser($user, array $opts = [])
    {
        $base = $this->userResourceUrl . "/{$this->normalizeUserIdentifier($user)}";

        $url = $base . $this->buildQuery($this->getDefaultUserOpts() + $opts);

        return User::wrap($this->authGet($url));
    }

    public function getAuthorizedUser(array $opts = [])
    {
        return $this->cache->get("authorized_user", function () use ($opts) {
            $base = $this->userResourceUrl . "/me";
            $url = $base . $this->buildQuery($this->getDefaultUserOpts() + $opts);

            return User::wrap($this->authGet($url));
        });
    }

    public function getAuthorizedUserPosts(array $opts = [])
    {
        return $this->getUserPosts("me", $opts);
    }

    public function getUserPosts($username, array $opts = [])
    {
        $identifier = $this->normalizeUserIdentifier($username);

        $url = "{$this->userResourceUrl}/{$identifier}/posts" . $this->buildQuery($opts);

        return PostCollection::wrap($this->authGet($url));
    }

    public function getAuthorizedUserMentions(array $opts = [])
    {
        return $this->getUserMentions("me", $opts);
    }

    public function getUserMentions($username, array $opts = [])
    {
        $identifier = $this->normalizeUserIdentifier($username);

        $url = "{$this->userResourceUrl}/{$identifier}/mentions" . $this->buildQuery($opts);

        return MentionCollection::wrap($this->authGet($url));
    }

    public function getChannel($identifier, array $opts = [])
    {
        $base = "{$this->channelResourceUrl}/{$identifier}";

        $url = $base . $this->buildQuery($this->getDefaultChannelOpts() + $opts);

        return Channel::wrap($this->client->get($url));
    }

    public function getChannelMessages($identifier, array $opts = [])
    {
        $base = "{$this->channelResourceUrl}/{$identifier}/messages";

        $url = $base . $this->buildQuery($this->getDefaultMessageOpts() + $opts);

        return MessageCollection::wrap($this->client->get($url));
    }

    public function searchPosts(array $opts = [])
    {
        $base = "{$this->postResourceUrl}/search";

        $url =  $base . $this->buildQuery($this->getDefaultSearchOpts() + $opts);

        return PostCollection::wrap($this->authGet($url));
    }

    protected function authGet($url, array $opts = [])
    {
        return $this->client->get($url, [
            "headers" => ["Authorization" => "Bearer {$this->accessToken}"],
        ] + $opts);
    }

    protected function buildQuery(array $opts = [])
    {
        if (!$opts) {
            return "";
        }

        $final = [];

        foreach ($opts as $key => $value) {
            if (is_bool($value)) {
                $final[$key] = $value ? 1 : 0;
            } else {
                $final[$key] = $value;
            }
        }

        return "?" . http_build_query($final);
    }

    protected function normalizeUserIdentifier($identifier)
    {
        if ($identifier instanceof User) {
            return $identifier->id;
        }

        if (is_array($identifier)) {
            return implode(",", array_map([$this, "normalizeUserIdentifier"], $identifier));
        }

        if (is_numeric($identifier)) {
            return $identifier;
        }

        if ($identifier === "me") {
            return $identifier;
        }

        return "@{$identifier}";
    }

    private function getDefaultUserOpts()
    {
        return [
            "include_annotations"      => true,
            "include_user_annotations" => true,
            "include_html"             => true,
        ];
    }

    private function getDefaultChannelOpts()
    {
        return [
            'include_annotations' => true,
            'channel_types'       => 'net.app.core.broadcast',
            'include_inactive'    => true,
        ];
    }

    private function getDefaultMessageOpts()
    {
        return [
            'include_message_annotations' => true,
            'include_user_annotations'    => false,
        ];
    }

    private function getDefaultSearchOpts()
    {
        return [
            'include_post_annotations' => true,
            "order" => "id",
        ];
    }
}
