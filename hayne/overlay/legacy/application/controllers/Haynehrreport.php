<?php
/**
 * REPORT-HR-01: monthly effective-absence report for HR/payroll.
 */
if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;

class Haynehrreport extends CI_Controller
{
    private const HEADERS = [
        'ID wniosku',
        'Pracownik',
        'Dział',
        'Rodzaj nieobecności',
        'Od',
        'Do',
        'Dni robocze w miesiącu',
        'Godziny w miesiącu',
        'Status',
        'Data złożenia',
        'Zatwierdził',
        'Data decyzji',
        'Anulowanie',
        'Kod płacowy',
    ];

    public function __construct()
    {
        parent::__construct();
        setUserContext($this);
        $this->assertAccess();
        $this->load->model('hayne_hr_monthly_report_model');
    }

    public function monthly(): void
    {
        $period = $this->selectedPeriod($this->input->get('period', TRUE));
        [$monthStart, $monthEnd] = $this->periodBounds($period);
        $rows = $this->hayne_hr_monthly_report_model->getMonthlyRows($monthStart, $monthEnd);

        $data = getUserContext($this);
        $data['title'] = 'Raport miesięczny dla kadr';
        $data['help'] = '';
        $data['period'] = $period;
        $data['rows'] = $rows;
        $data['headers'] = self::HEADERS;
        $data['missing_payroll_codes'] = $this->hasMissingPayrollCodes($rows);

        $this->load->view('templates/header', $data);
        $this->load->view('menu/index', $data);
        $this->load->view('haynehrreport/monthly', $data);
        $this->load->view('templates/footer');
    }

    public function xlsx(): void
    {
        $period = $this->selectedPeriod($this->input->get('period', TRUE));
        [$monthStart, $monthEnd] = $this->periodBounds($period);
        $rows = $this->hayne_hr_monthly_report_model->getMonthlyRows($monthStart, $monthEnd);

        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Raport');
        $sheet->mergeCells('A1:N1');
        $sheet->setCellValue('A1', 'HAYNE Leave — raport miesięczny dla kadr');
        $sheet->getStyle('A1')->getFont()->setBold(TRUE)->setSize(14);

        $sheet->setCellValue('A3', 'Okres raportu');
        $sheet->setCellValue('B3', $period);
        $sheet->setCellValue('D3', 'Wygenerowano');
        $sheet->setCellValue('E3', date('Y-m-d H:i:s'));
        $sheet->setCellValue('G3', 'Wygenerował');
        $sheet->setCellValue('H3', $this->actorName());
        $sheet->setCellValue('K3', 'Źródło');
        $sheet->setCellValue('L3', 'HAYNE Leave');
        $sheet->getStyle('A3:N3')->getFont()->setBold(TRUE);

        foreach (self::HEADERS as $index => $header) {
            $sheet->setCellValue(columnName($index + 1) . '5', $header);
        }
        $sheet->getStyle('A5:N5')->getFont()->setBold(TRUE);
        $sheet->getStyle('A5:N5')->getAlignment()->setHorizontal(Alignment::HORIZONTAL_CENTER);
        $sheet->getStyle('A5:N5')->getAlignment()->setWrapText(TRUE);

        $line = 6;
        foreach ($rows as $row) {
            foreach ($this->exportValues($row) as $index => $value) {
                $sheet->setCellValue(columnName($index + 1) . $line, $value);
            }
            $line++;
        }

        $lastDataRow = max(5, $line - 1);
        $sheet->freezePane('A6');
        $sheet->setAutoFilter('A5:N' . $lastDataRow);
        for ($column = 1; $column <= count(self::HEADERS); $column++) {
            $sheet->getColumnDimension(columnName($column))->setAutoSize(TRUE);
        }

        $footerRow = $line + 1;
        $sheet->mergeCells('A' . $footerRow . ':N' . $footerRow);
        $sheet->setCellValue(
            'A' . $footerRow,
            'Raport pokazuje tylko nieobecności skuteczne w wybranym miesiącu; przy wniosku obejmującym dwa miesiące ' .
            'liczone są wyłącznie dni/godziny przypadające na raportowany miesiąc. Uzasadnienia i dane wrażliwe nie są eksportowane.'
        );
        $sheet->getStyle('A' . $footerRow)->getAlignment()->setWrapText(TRUE);

        writeSpreadsheet($spreadsheet, 'HAYNE_Leave_raport_miesieczny_' . $period);
    }

    public function csv(): void
    {
        $period = $this->selectedPeriod($this->input->get('period', TRUE));
        [$monthStart, $monthEnd] = $this->periodBounds($period);
        $rows = $this->hayne_hr_monthly_report_model->getMonthlyRows($monthStart, $monthEnd);

        $handle = fopen('php://temp', 'w+');
        if ($handle === FALSE) {
            show_error('Nie udało się przygotować eksportu CSV.', 500);
            return;
        }
        fwrite($handle, "\xEF\xBB\xBF");
        fputcsv($handle, self::HEADERS, ';', '"', '');
        foreach ($rows as $row) {
            fputcsv($handle, $this->exportValues($row), ';', '"', '');
        }
        rewind($handle);
        $csv = stream_get_contents($handle);
        fclose($handle);

        $filename = 'HAYNE_Leave_raport_miesieczny_' . $period . '.csv';
        $this->output
            ->set_content_type('text/csv', 'UTF-8')
            ->set_header('Content-Disposition: attachment; filename="' . $filename . '"')
            ->set_output($csv === FALSE ? '' : $csv);
    }

    /** @return array{0:string,1:string} */
    private function periodBounds(string $period): array
    {
        $monthStart = $period . '-01';
        $monthEnd = date('Y-m-t', strtotime($monthStart . ' 00:00:00'));
        return [$monthStart, $monthEnd];
    }

    private function selectedPeriod($raw): string
    {
        $period = is_string($raw) ? trim($raw) : '';
        if (preg_match('/^(\d{4})-(0[1-9]|1[0-2])$/', $period, $matches) === 1) {
            $year = (int) $matches[1];
            $month = (int) $matches[2];
            if ($year >= 2000 && $year <= 2100 && checkdate($month, 1, $year)) {
                return $period;
            }
        }
        return date('Y-m', strtotime('first day of previous month'));
    }

    private function actorName(): string
    {
        $name = trim((string) $this->fullname);
        return $name === '' ? 'Użytkownik HAYNE' : $name;
    }

    /** @param array<int, array<string, mixed>> $rows */
    private function hasMissingPayrollCodes(array $rows): bool
    {
        foreach ($rows as $row) {
            if (trim((string) ($row['payroll_code'] ?? '')) === '') {
                return TRUE;
            }
        }
        return FALSE;
    }

    /** @return array<int, int|float|string> */
    private function exportValues(array $row): array
    {
        return [
            (int) $row['id'],
            (string) $row['employee'],
            (string) $row['department'],
            (string) $row['type_name'],
            (string) $row['startdate'],
            (string) $row['enddate'],
            (float) $row['days_month'],
            (float) $row['hours_month'],
            (string) $row['status'],
            (string) $row['submitted_at'],
            (string) $row['approved_by'],
            (string) $row['approved_at'],
            (string) $row['cancellation'],
            (string) $row['payroll_code'],
        ];
    }

    private function assertAccess(): void
    {
        if (!$this->is_hr && !$this->is_admin) {
            show_error('Forbidden', 403);
        }
    }
}
