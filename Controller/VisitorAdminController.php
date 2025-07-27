<?php

namespace Beast\VisitorTrackerBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Beast\VisitorTrackerBundle\Service\VisitorLogFetcher;

class VisitorAdminController extends AbstractController
{
    #[Route('/admin/visitor/sysadmin', name: 'visitor_sysadmin_dashboard')]
    public function sysadmin(VisitorLogFetcher $fetcher): Response
    {
        $summary = $fetcher->fetchSummarizeLogs([
            'from' => '-1 day',
            'to' => 'now'
        ]);

        return $this->render('@BeastVisitorTracker/sysadmin_dashboard.html.twig', [
            'data' => $summary,
        ]);
    }
}

