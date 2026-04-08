<?php

namespace App\Service;

use App\Entity\ContratAssurance;
use Dompdf\Dompdf;
use Psr\Log\LoggerInterface;
use Twig\Environment as Twig;

/**
 * Service for generating PDF contracts
 */
class ContractPdfService
{
    private LoggerInterface $logger;
    private Twig $twig;

    public function __construct(LoggerInterface $logger, Twig $twig)
    {
        $this->logger = $logger;
        $this->twig = $twig;
    }

    /**
     * Generate PDF from contract
     * 
     * @param ContratAssurance $contract
     * @return string PDF binary content
     * @throws \Exception
     */
    public function generatePdf(ContratAssurance $contract): string
    {
        try {
            // Render HTML from template
            $html = $this->twig->render('contract/pdf.html.twig', [
                'contract' => $contract,
                'generateDate' => new \DateTime(),
            ]);

            // Create Dompdf instance
            $dompdf = new Dompdf();
            
            // Set options for better formatting
            $options = $dompdf->getOptions();
            $options->setDefaultPaperSize('A4');
            $options->setDefaultPaperOrientation('portrait');
            $dompdf->setOptions($options);

            // Load HTML
            $dompdf->loadHtml($html);

            // Render PDF
            $dompdf->render();

            // Get PDF as string
            $pdfContent = $dompdf->output();

            $this->logger->info(sprintf(
                'Successfully generated PDF for contract #%s',
                $contract->getNumeroContrat()
            ));

            return $pdfContent;

        } catch (\Exception $e) {
            $this->logger->error(sprintf(
                'Error generating PDF for contract #%s: %s',
                $contract->getNumeroContrat(),
                $e->getMessage()
            ));
            throw $e;
        }
    }

    /**
     * Get filename for contract PDF
     */
    public function getFilename(ContratAssurance $contract): string
    {
        $timestamp = $contract->getDateSignature() ? $contract->getDateSignature()->format('Y-m-d') : 'unknown';
        return sprintf(
            'Contract_%s_%s.pdf',
            $contract->getNumeroContrat(),
            $timestamp
        );
    }
}
