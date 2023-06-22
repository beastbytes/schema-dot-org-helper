<?php

use Robo\Symfony\ConsoleIO;
use Robo\Tasks;

/**
 * This is project's console commands configuration for Robo task runner.
 *
 * @see https://robo.li/
 */
class RoboFile extends Tasks
{
    public function release(ConsoleIO $io, $opt = [
        'branch' => 'main',
        'what' => 'patch'
    ]): void
    {
        $result = $this->taskSemVer()
            ->increment($opt['what'])
            ->run();

        $tag = $result->getMessage();

        $this->say("Releasing $tag");

        $this->clean($io);
        $this->publishGit($opt['branch'], ['tag' => $tag]);
    }

    public function clean(ConsoleIO $io): void
    {
        $io->say('Cleaning up');
        $this->taskCleanDir(['logs'])->run();
        $this->taskDeleteDir('logs')->run();
    }

    /**
     * @desc creates a new version tag and pushes to GitHub
     * @param null $branch
     * @param array $opt
     */
    public function publishGit($branch = null, $opt = ['tag' => null])
    {
        $this->say('Pushing ' . $opt['tag'] . ' to GitHub');
        $this->taskExec('git tag ' . $opt['tag'])
            ->run();
        $this->taskExec("git push origin $branch --tags")
            ->run();
    }
}
