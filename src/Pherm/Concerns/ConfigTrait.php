<?php

namespace MilesChou\Pherm\Concerns;

use MilesChou\Pherm\Stty;

trait ConfigTrait
{
    /**
     * @var int
     *
     * @see https://en.wikipedia.org/wiki/ANSI_escape_code#8-bit
     */
    private $defaultBackground = 0;

    /**
     * @var int
     *
     * @see https://en.wikipedia.org/wiki/ANSI_escape_code#8-bit
     */
    private $defaultForeground = 0;

    /**
     * @var int
     */
    private $colourSupport;

    /**
     * @var bool
     */
    private $echoBack;

    /**
     * @var int
     */
    private $height;

    /**
     * @var bool
     */
    private $isCanonical;

    /**
     * @var Stty
     */
    private $stty;

    /**
     * @var int
     */
    private $width;

    /**
     * @param int $defaultBackground
     * @return static
     */
    public function defaultBackground(int $defaultBackground)
    {
        $this->defaultBackground = $defaultBackground;

        return $this;
    }

    /**
     * @param int $defaultForeground
     * @return static
     */
    public function defaultForeground(int $defaultForeground)
    {
        $this->defaultForeground = $defaultForeground;

        return $this;
    }

    public function disableCanonicalMode()
    {
        $this->stty->exec('-icanon');
        $this->isCanonical = false;

        return $this;
    }

    public function disableEchoBack()
    {
        $this->stty->exec('-echo');
        $this->echoBack = false;

        return $this;
    }

    public function enableCanonicalMode()
    {
        $this->stty->exec('icanon');
        $this->isCanonical = true;

        return $this;
    }

    public function enableEchoBack()
    {
        $this->stty->exec('echo');
        $this->echoBack = true;

        return $this;
    }

    public function getColourSupport(): int
    {
        return $this->colourSupport;
    }

    /**
     * @return int
     */
    public function height(): int
    {
        return $this->height;
    }

    public function isCanonicalMode(): bool
    {
        return $this->isCanonical;
    }

    public function isEchoBack(): bool
    {
        return $this->echoBack;
    }

    /**
     * @param Stty $stty
     * @return static
     */
    public function setStty(Stty $stty)
    {
        $this->stty = $stty;

        return $this;
    }

    /**
     * @return int
     */
    public function width(): int
    {
        return $this->width;
    }

    protected function prepareConfiguration()
    {
        if (null === $this->stty) {
            $this->setStty(new Stty());
        }

        $this->stty->store();
        $parsed = $this->stty->parseAll();

        $this->echoBack = $parsed['echo'];
        $this->isCanonical = $parsed['icanon'];
        $this->height = $parsed['rows'];
        $this->width = $parsed['columns'];

        $this->colourSupport = (int)exec('tput colors');
    }
}