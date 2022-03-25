<?php


namespace App\Model\Task;


use App\Configuration;

class Title
{
    //private string $title;
    private string $code;
    private string $name;

    /**
     * PZ-2541 - Překlady srbsko
     *  - code -> 2541
     *  - name -> Překlady srbsko
     */
    public function __construct(string $title)
    {
        $prefix = Configuration::get("jira/prefix"); /// Jira prefix for issue
        // ~.*PZ\s?(?:-|\/|\:|\s)\s?(\d+)(?:[a-zA-Z])?(?:\s|:|\/|\||_|\d|-)*(.*)~
        $revert = str_starts_with("Revert ", $title);
        $regex = "~.*" . $prefix . "\s?(?:-|\/|\:|\s)\s?(\d+)[a-zA-Z]?(?:\s|:|\/|\||_|\d|-)*(.*)" . "~i";
        preg_match($regex, $title, $matched);

        // Prevent not found case
        if (empty($matched)) {
            $matched = [
                0 => $title,
                1 => 0,
                2 => $title,
            ];
        }

        $this->code  = $matched[1];
        $this->name  = $matched[2];

        if ($revert) $this->name = "Revert: " . $this->name;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getCode($full = false): string
    {
        if ($full) return Configuration::get('jira/prefix') . "-" . $this->code;
        return $this->code;
    }
}