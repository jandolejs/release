<?php


namespace App;

use App\Model\Git\GitHub;
use App\Model\Statistics;
use Nette\Database\Explorer;
use Nette;
use Nette\DI\Attributes\Inject;

/**
 * Class Presenter
 * @package App
 */
class Presenter extends Nette\Application\UI\Presenter
{

    #[inject] public Statistics $statistics;
    #[Inject] public Explorer $database;
    #[Inject] public Configuration $config;
    #[Inject] public GitHub $gitHub;

    // This is because config needs to be loaded on beginning
    public function __construct(Configuration $c) {
        parent::__construct();
    }

    public function startup()
    {
        parent::startup();

        // Check internet connection
        $connected = @fsockopen("google.com", 80);
        if (!$connected) $this->flashMessage("Internet connection is missing!", "danger");
        else fclose($connected);

        $this->statistics->visit($this->getHttpRequest()->getUrl()->getPath(), 'visit');
    }

    /**
     * @param string $resource
     * @param string $privilege
     * @throws \Nette\Application\AbortException
     */
    public function permit(string $resource, string $privilege = '')
    {
        if (!$this->getUser()->isAllowed($resource, $privilege)) {
            $this->flashMessage(
                "You dont have permissions for this ($resource->" . ($privilege ?: '[/]') . ")",
                "warning");
            $this->redirect("Homepage:");
        }
    }

    /**
     * @param string $text
     * @throws \Nette\Application\AbortException
     */
    public function sendErrorText(string $text)
    {
        $this->getHttpResponse()->setContentType('text/plain', 'UTF-8');
        $this->getHttpResponse()->setCode(Nette\Http\IResponse::S401_UNAUTHORIZED);
        $this->sendResponse(new Nette\Application\Responses\TextResponse($text));
    }


    public function beforeRender()
    {
        parent::beforeRender();
        $this->statistics->visit($this->getHttpRequest()->getUrl()->getPath(), 'render');

        // Change markdown link to html link
        $this->template->addFilter('mdLink', function($original) {
            $regex = '~\[(.+?)\]\(((https?:\/)?\/[\w\d.\/?=\-#]+)\)~m'; /** @noinspection HtmlUnknownTarget because $2 is not path */
            return preg_replace($regex, "<a href='$2' target='_blank'>$1</a>", htmlspecialchars($original));
        });

        // Change newline to <br> tag
        $this->template->addFilter('nl2br', function($original) {
            return nl2br($original);
        });

        $this->template->addFilter('niceGitName', function($original) {
            if (isset(Configuration::get("github/usernames")[$original]))
                return Configuration::get("github/usernames")[$original];
            return $original;
        });

        $this->template->addFilter('githubPullLink', function($original) {
            return $this->gitHub::getPullLink($original);
        });
    }
}
