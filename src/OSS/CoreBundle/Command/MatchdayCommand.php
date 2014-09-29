<?php

namespace OSS\CoreBundle\Command;

use OSS\CoreBundle\Entity\Fixture;
use OSS\CoreBundle\Entity\GameDate;
use OSS\CoreBundle\Entity\League;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

class MatchdayCommand extends BaseCommand
{
    protected function configure()
    {
        $this->setName('oss:matchday');
        $this->setDescription('Evaluates the current matchday');
        $this->addArgument('count', InputArgument::OPTIONAL, 'How many times shall the matchday command be executed?', 1);
    }

    protected function execute(InputInterface $input, OutputInterface $output)
    {
        for ($i = 1; $i <= $input->getArgument('count'); $i++) {
            $this->executeOnce($output);
        }
    }

    /**
     * @param OutputInterface $output
     */
    private function executeOnce(OutputInterface $output)
    {
        /** @var GameDate $gameDate */
        $gameDate = $this->getGameDateRepository()->findOneBy(array());

        $this->executeMatches($gameDate, $output);
        $this->getTrainingService()->handleTrainingValueReduction();
        $this->getTrainingService()->handleTraining();
        $this->getTransferService()->handleTransfers();

        $gameDate->incrementWeek();
        $this->executeHalfOfSeason($gameDate);
        $this->executeStartOfSeason($gameDate);
        $this->getEntityManager()->flush();
    }

    /**
     * @param GameDate $gameDate
     * @param OutputInterface $output
     */
    private function executeMatches(GameDate $gameDate, OutputInterface $output)
    {
        /** @var Fixture[] $matches */
        $matches = $this->getFixtureRepository()->findByGameDate($gameDate);
        $progress = new ProgressBar($output, count($matches));
        $progress->start();
        foreach ($matches as $match) {
            $this->getLineupService()->createFixtureLineup($match);
            $this->getMatchEvaluationService()->evaluateCompleteMatch($match);
            $progress->advance();
        }
        $progress->finish();
    }

    private function executeHalfOfSeason(GameDate $gameDate)
    {
        if ($gameDate->getWeek() == 18) {
            $this->getTrainingService()->handleSkillUpdate();
        }
    }

    /**
     * @param GameDate $gameDate
     */
    private function executeStartOfSeason(GameDate $gameDate)
    {
        if ($gameDate->getWeek() == 1) {
            $this->getTrainingService()->handleSkillUpdate();
            /** @var League[] $leagues */
            $leagues = $this->getLeagueRepository()->findAll();
            foreach ($leagues as $league) {
                $league->createFinalPositions($gameDate->getSeason() - 1);
                $league->resetStandings();
            }
            $this->getTransferOfferRepository()->removeAll();
            $this->getFixtureService()->createFixtures($gameDate->getSeason());
        }
    }
}
