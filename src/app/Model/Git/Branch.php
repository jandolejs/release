<?php


namespace App\Model\Git;


use App\Exceptions\BranchException;
use JetBrains\PhpStorm\Pure;

class Branch
{
    private string $name; // name of branch 'release/123'
    private string $sha;  // sha of branch 'a7cF2Gy...'

    /**
     * @throws \App\Exceptions\BranchException
     */
    public function __construct(string $name, string $sha)
    {
        if (empty($name)) throw new BranchException("Branch name can not be empty");
        $name = (string)preg_replace("~^refs\/heads\/~", "", $name);

        $this->name = $name;
        $this->sha  = $sha;
    }


    #[Pure] public function __toString(): string
    {
        return $this->getName();
    }


    public function getName(): string
    {
        return $this->name;
    }

    public function getSha(): string
    {
        return $this->sha;
    }

}
