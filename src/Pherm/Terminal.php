<?php

namespace MilesChou\Pherm;

use InvalidArgumentException;
use MilesChou\Pherm\Binding\Key;
use MilesChou\Pherm\Concerns\BufferTrait;
use MilesChou\Pherm\Concerns\ConfigTrait;
use MilesChou\Pherm\Concerns\InstantOutputTrait;
use MilesChou\Pherm\Concerns\IoTrait;
use MilesChou\Pherm\Contracts\Attribute;
use MilesChou\Pherm\Contracts\Cursor as CursorContract;
use MilesChou\Pherm\Contracts\InputStream as InputContract;
use MilesChou\Pherm\Contracts\OutputStream as OutputContract;
use MilesChou\Pherm\Contracts\Terminal as TerminalContract;
use MilesChou\Pherm\Input\InputStream;
use MilesChou\Pherm\Output\Attributes\Color256;
use MilesChou\Pherm\Output\OutputStream;
use MilesChou\Pherm\Support\Char;

class Terminal implements TerminalContract
{
    use BufferTrait;
    use ConfigTrait;
    use InstantOutputTrait;
    use IoTrait;

    /**
     * @var Attribute
     */
    private $attribute;

    /**
     * @var Key
     */
    private $keyBinding;

    /**
     * @var int|null
     */
    private $lastFg = Attribute::INVALID;

    /**
     * @var int|null
     */
    private $lastBg = Attribute::INVALID;

    /**
     * @var Renderer
     */
    private $renderer;

    /**
     * @param InputContract|null $input
     * @param OutputContract|null $output
     * @param Control|null $control
     */
    public function __construct(InputContract $input = null, OutputContract $output = null, Control $control = null)
    {
        $input = $input ?? new InputStream();
        $output = $output ?? new OutputStream();
        $control = $control ?? new Control();

        $cursor = new Cursor($this, $control);

        $this->setInput($input);
        $this->setOutput($output);
        $this->setControl($control);
        $this->setCursor($cursor);

        // TODO: Now just use Color256
        $this->attribute = new Color256();
    }

    /**
     * Proxy to Attribute object
     *
     * @param int|null $fg
     * @param int|null $bg
     * @return static
     */
    public function attribute(?int $fg = null, ?int $bg = null)
    {
        if ($this->isInstantOutput()) {
            if ($fg === $this->lastFg && $bg === $this->lastBg) {
                return $this;
            }

            $this->output->write($this->attribute->generate($fg, $bg));
        }

        $this->lastFg = $fg;
        $this->lastBg = $bg;

        return $this;
    }

    /**
     * @return static
     */
    public function bootstrap()
    {
        $this->prepareConfiguration();
        $this->prepareCellBuffer();

        $this->renderer = new Renderer($this);

        return $this;
    }

    /**
     * Clear screen and buffer
     *
     * @param int|null $fg
     * @param int|null $bg
     * @return static
     */
    public function clear(?int $fg = null, ?int $bg = null)
    {
        // Clear terminal
        $this->output->write("\033[2J");

        // Clear backend buffer
        $this->getCellBuffer()->clear($fg, $bg);

        // Reset cursor
        $this->cursor->position(1, 1);

        return $this;
    }

    /**
     * @return Cursor
     */
    public function cursor(): Cursor
    {
        return $this->cursor;
    }

    /**
     * @return static
     */
    public function disableInstantOutput()
    {
        $this->instantOutput = false;

        if (method_exists($this->output, 'disableInstantOutput')) {
            $this->output->disableInstantOutput();
        }

        return $this;
    }

    /**
     * @return static
     */
    public function enableInstantOutput()
    {
        $this->instantOutput = true;

        if (method_exists($this->output, 'enableInstantOutput')) {
            $this->output->enableInstantOutput();
        }

        return $this;
    }

    /**
     * Flush buffer to output
     */
    public function flush(): void
    {
        $this->renderer->renderBuffer($this->getCellBuffer());

        $this->output->flush();
    }

    public function getAttribute(): Attribute
    {
        return $this->attribute;
    }

    public function getCursor(): CursorContract
    {
        return $this->cursor();
    }

    /**
     * @return Key
     */
    public function keyBinding()
    {
        if (null === $this->keyBinding) {
            $this->keyBinding = new Key($this);
        }

        return $this->keyBinding;
    }

    /**
     * @param int $x
     * @param int $y
     * @return TerminalContract
     */
    public function moveCursor(int $x, int $y): TerminalContract
    {
        return $this->cursor->move($x, $y);
    }

    public function read(int $bytes): string
    {
        $buffer = '';

        $this->input->read($bytes, function ($data) use (&$buffer) {
            $buffer .= $data;
        });

        return $buffer;
    }

    public function write(string $buffer)
    {
        if ($this->isInstantOutput()) {
            $this->output->write($buffer);
        } else {
            foreach (Char::charsToArray($buffer) as $char) {
                $this->writeChar($char);
            }
        }
    }

    /**
     * @param string $char
     * @return static
     */
    public function writeChar(string $char)
    {
        if (mb_strlen($char) > 1) {
            throw new InvalidArgumentException('Char must be only one mbstring');
        }

        if ($this->isInstantOutput()) {
            $this->output->write($char);
        } else {
            [$x, $y] = $this->cursor->last();

            if ($char === "\n") {
                if ($y + 1 > $this->height) {
                    return $this;
                }
                $this->cursor->position(1, $y + 1);

                return $this;
            }

            $this->getCellBuffer()->set($x, $y, $char, $this->lastFg, $this->lastBg);

            if ($x + 1 > $this->width) {
                if ($y + 1 > $this->height) {
                    return $this;
                }
                $x = 0;
                $y++;
            }

            $this->cursor->position($x + 1, $y);
        }

        return $this;
    }

    /**
     * Restore the original terminal configuration on shutdown.
     */
    public function __destruct()
    {
        $this->stty->restore();
        $this->enableCursor();
    }
}
