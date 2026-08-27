<?php
/**
 * Data source for the HAYNE monthly HR/payroll absence report.
 *
 * The model deliberately selects only fields required by payroll. Sensitive
 * request details remain outside the report surface.
 */
if (!defined('BASEPATH')) {
    exit('No direct script access allowed');
}

class Hayne_hr_monthly_report_model extends CI_Model
{
    private const USAGE_CORRECTION_PREFIX = '[HAYNE_USAGE_CORRECTION|';

    public function __construct()
    {
        parent::__construct();
        $this->load->model('dayoffs_model');
        $this->load->model('leaves_model');
        $this->config->load('hayne_payroll_codes');
    }

    /**
     * Return effective absences overlapping one calendar month.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getMonthlyRows(string $monthStart, string $monthEnd): array
    {
        $this->assertDateRange($monthStart, $monthEnd);

        $this->db->select(
            "leaves.id, leaves.employee, leaves.type AS type_id, leaves.startdate, leaves.enddate, " .
            "leaves.startdatetype, leaves.enddatetype, leaves.status, " .
            "CONCAT_WS(' ', users.firstname, users.lastname) AS employee_name, " .
            "organization.name AS department, types.name AS type_name",
            FALSE
        );
        $this->db->from('leaves');
        $this->db->join('users', 'users.id = leaves.employee');
        $this->db->join('organization', 'organization.id = users.organization', 'left');
        $this->db->join('types', 'types.id = leaves.type');
        $this->db->where_in('leaves.status', [LMS_ACCEPTED, LMS_CANCELLATION]);
        $this->db->where('leaves.startdate <=', $monthEnd);
        $this->db->where('leaves.enddate >=', $monthStart);
        $this->db->where(
            "(leaves.cause IS NULL OR leaves.cause NOT LIKE '" . self::USAGE_CORRECTION_PREFIX . "%')",
            NULL,
            FALSE
        );
        $this->db->order_by('users.lastname', 'asc');
        $this->db->order_by('users.firstname', 'asc');
        $this->db->order_by('leaves.startdate', 'asc');
        $this->db->order_by('leaves.id', 'asc');
        $sourceRows = $this->db->get()->result_array();

        if (empty($sourceRows)) {
            return [];
        }

        $leaveIds = array_map('intval', array_column($sourceRows, 'id'));
        $audit = $this->auditMap($leaveIds);
        $payrollCodes = $this->config->item('hayne_monthly_payroll_codes');
        if (!is_array($payrollCodes)) {
            $payrollCodes = [];
        }

        $result = [];
        foreach ($sourceRows as $source) {
            $leaveId = (int) $source['id'];
            $employeeId = (int) $source['employee'];
            $typeId = (int) $source['type_id'];
            $status = (int) $source['status'];
            $clippedStart = max((string) $source['startdate'], $monthStart);
            $clippedEnd = min((string) $source['enddate'], $monthEnd);
            $startType = ((string) $source['startdate'] < $monthStart)
                ? 'Morning'
                : (string) $source['startdatetype'];
            $endType = ((string) $source['enddate'] > $monthEnd)
                ? 'Afternoon'
                : (string) $source['enddatetype'];

            $daysOff = $this->dayoffs_model->listOfDaysOffBetweenDates(
                $employeeId,
                $clippedStart,
                $clippedEnd
            );
            $duration = $this->leaves_model->actualLengthAndDaysOff(
                $clippedStart,
                $clippedEnd,
                $startType,
                $endType,
                $daysOff,
                FALSE
            );

            $rowAudit = $audit[$leaveId] ?? [
                'created_at' => '',
                'requested_at' => '',
                'approved_at' => '',
                'approved_by' => '',
            ];

            $result[] = [
                'id' => $leaveId,
                'employee' => trim((string) $source['employee_name']),
                'department' => (string) ($source['department'] ?? ''),
                'type_name' => (string) $source['type_name'],
                'startdate' => (string) $source['startdate'],
                'enddate' => (string) $source['enddate'],
                'days_month' => (float) $duration['length'],
                // HAYNE currently records day/half-day absences, not hourly units.
                // Do not manufacture an 8h/day conversion for payroll.
                'hours_month' => 0,
                'status' => $status === LMS_CANCELLATION ? 'Anulowanie w toku' : 'Zaakceptowany',
                'submitted_at' => $rowAudit['requested_at'] !== ''
                    ? $rowAudit['requested_at']
                    : $rowAudit['created_at'],
                'approved_by' => $rowAudit['approved_by'],
                'approved_at' => $rowAudit['approved_at'],
                'cancellation' => $status === LMS_CANCELLATION ? 'W toku' : 'Nie',
                'payroll_code' => trim((string) ($payrollCodes[$typeId] ?? '')),
            ];
        }

        return $result;
    }

    /**
     * Build request/decision audit facts without exposing request contents.
     *
     * @param array<int> $leaveIds
     * @return array<int, array<string, string>>
     */
    private function auditMap(array $leaveIds): array
    {
        if (empty($leaveIds)) {
            return [];
        }

        $this->db->select(
            "leaves_history.id, leaves_history.change_id, leaves_history.change_type, " .
            "leaves_history.status, leaves_history.change_date, " .
            "CONCAT_WS(' ', actor.firstname, actor.lastname) AS actor_name",
            FALSE
        );
        $this->db->from('leaves_history');
        $this->db->join('users actor', 'actor.id = leaves_history.changed_by', 'left');
        $this->db->where_in('leaves_history.id', $leaveIds);
        $this->db->order_by('leaves_history.id', 'asc');
        $this->db->order_by('leaves_history.change_id', 'asc');
        $history = $this->db->get()->result_array();

        $result = [];
        foreach ($history as $entry) {
            $leaveId = (int) $entry['id'];
            if (!isset($result[$leaveId])) {
                $result[$leaveId] = [
                    'created_at' => '',
                    'requested_at' => '',
                    'approved_at' => '',
                    'approved_by' => '',
                ];
            }

            if ($result[$leaveId]['created_at'] === '' && (int) $entry['change_type'] === 1) {
                $result[$leaveId]['created_at'] = (string) $entry['change_date'];
            }
            if ($result[$leaveId]['requested_at'] === '' && (int) $entry['status'] === LMS_REQUESTED) {
                $result[$leaveId]['requested_at'] = (string) $entry['change_date'];
            }
            if ($result[$leaveId]['approved_at'] === '' && (int) $entry['status'] === LMS_ACCEPTED) {
                $result[$leaveId]['approved_at'] = (string) $entry['change_date'];
                $result[$leaveId]['approved_by'] = trim((string) ($entry['actor_name'] ?? ''));
            }
        }

        return $result;
    }

    private function assertDateRange(string $startDate, string $endDate): void
    {
        $start = DateTime::createFromFormat('!Y-m-d', $startDate);
        $end = DateTime::createFromFormat('!Y-m-d', $endDate);
        if (
            $start === FALSE || $start->format('Y-m-d') !== $startDate ||
            $end === FALSE || $end->format('Y-m-d') !== $endDate ||
            $startDate > $endDate
        ) {
            throw new InvalidArgumentException('Nieprawidłowy zakres raportu miesięcznego.');
        }
    }
}
