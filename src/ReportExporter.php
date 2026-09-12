<?php
declare(strict_types=1);

final class ReportExporter
{
    public static function payrollCsv(int $companyId,string $from,string $to):never
    {
        $sql='SELECT e.registration,e.name,e.department,SUM(d.worked_minutes) worked,SUM(d.expected_minutes) expected,SUM(d.overtime_minutes) overtime,SUM(d.delay_minutes) delays,SUM(d.night_minutes) night FROM daily_calculations d JOIN employees e ON e.id=d.employee_id WHERE d.company_id=? AND d.work_date BETWEEN ? AND ? GROUP BY e.id,e.registration,e.name,e.department ORDER BY e.name';
        $stmt=db()->prepare($sql);$stmt->execute([$companyId,$from,$to]);
        header('Content-Type: text/csv; charset=UTF-8');header('Content-Disposition: attachment; filename="resumo-folha-'.$from.'-'.$to.'.csv"');
        $out=fopen('php://output','wb');fwrite($out,"\xEF\xBB\xBF");fputcsv($out,['Matrícula','Colaborador','Setor','Trabalhadas','Previstas','Extras','Atrasos','Noturnas','Saldo'], ';');
        foreach($stmt as $r){fputcsv($out,[$r['registration'],$r['name'],$r['department'],self::hours($r['worked']),self::hours($r['expected']),self::hours($r['overtime']),self::hours($r['delays']),self::hours($r['night']),self::signed((int)$r['worked']-(int)$r['expected'])],';');}fclose($out);exit;
    }
    public static function hours(int|string|null $minutes):string{$m=(int)$minutes;return sprintf('%02d:%02d',intdiv(abs($m),60),abs($m)%60);}
    public static function signed(int $minutes):string{return($minutes>=0?'+':'-').self::hours($minutes);}
}
