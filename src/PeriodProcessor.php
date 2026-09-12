<?php
declare(strict_types=1);

final class PeriodProcessor
{
    public function process(int $companyId,string $from,string $to,int $userId):array
    {
        $employees=db()->prepare("SELECT * FROM employees WHERE company_id=? AND status='active'");$employees->execute([$companyId]);$days=0;$issues=0;
        foreach($employees as $employee){$date=new DateTimeImmutable($from);$end=new DateTimeImmutable($to);
            while($date<=$end){$day=$date->format('Y-m-d');$schedule=$this->schedule((int)$employee['id'],$day);if(!$schedule||!$this->isWorkday($date,$schedule)){$date=$date->modify('+1 day');continue;}
                $p=db()->prepare('SELECT punched_at FROM punches WHERE employee_id=? AND punched_at>=? AND punched_at<? ORDER BY punched_at');$p->execute([$employee['id'],$day.' 00:00:00',$date->modify('+1 day')->format('Y-m-d').' 12:00:00']);
                $result=TimeCalculator::analyseDay(array_column($p->fetchAll(),'punched_at'),$schedule,(int)$schedule['tolerance_minutes']);$this->save($companyId,(int)$employee['id'],$day,$result);$days++;$issues+=count($result['issues']);
                foreach($result['issues'] as $kind)$this->issue($companyId,(int)$employee['id'],$day,$kind,$userId);
                $date=$date->modify('+1 day');
            }
        }return['days'=>$days,'issues'=>$issues];
    }
    private function schedule(int $employee,string $date):array|false{$s=db()->prepare('SELECT s.*,a.starts_on assignment_start FROM employee_schedule_assignments a JOIN schedules s ON s.id=a.schedule_id WHERE a.employee_id=? AND a.starts_on<=? AND (a.ends_on IS NULL OR a.ends_on>=?) ORDER BY a.starts_on DESC LIMIT 1');$s->execute([$employee,$date,$date]);return$s->fetch();}
    private function isWorkday(DateTimeImmutable $date,array $schedule):bool
    {
        if(($schedule['type']??'')==='12x36'){$start=new DateTimeImmutable($schedule['assignment_start']);return((int)$start->diff($date)->format('%a'))%2===0;}
        $weekday=(int)$date->format('N');if($weekday===7)return false;
        return !($weekday===6&&(int)($schedule['weekly_minutes']??0)<=2400);
    }
    private function save(int $company,int $employee,string $day,array $r):void{$exists=db()->prepare('SELECT id FROM daily_calculations WHERE company_id=? AND employee_id=? AND work_date=?');$exists->execute([$company,$employee,$day]);$id=$exists->fetchColumn();if($id){db()->prepare('UPDATE daily_calculations SET worked_minutes=?,expected_minutes=?,overtime_minutes=?,delay_minutes=?,night_minutes=?,issues=?,processed_at=CURRENT_TIMESTAMP WHERE id=?')->execute([$r['worked_minutes'],$r['expected_minutes'],$r['overtime_minutes'],$r['delay_minutes'],$r['night_minutes'],json_encode($r['issues']),$id]);}else{db()->prepare('INSERT INTO daily_calculations (company_id,employee_id,work_date,worked_minutes,expected_minutes,overtime_minutes,delay_minutes,night_minutes,issues) VALUES (?,?,?,?,?,?,?,?,?)')->execute([$company,$employee,$day,$r['worked_minutes'],$r['expected_minutes'],$r['overtime_minutes'],$r['delay_minutes'],$r['night_minutes'],json_encode($r['issues'])]);}}
    private function issue(int $company,int $employee,string $day,string $kind,int $user):void{$q=db()->prepare("SELECT COUNT(*) FROM adjustments WHERE company_id=? AND employee_id=? AND work_date=? AND kind=? AND status='pending'");$q->execute([$company,$employee,$day,$kind]);if(!$q->fetchColumn())db()->prepare('INSERT INTO adjustments (company_id,employee_id,work_date,kind,status,reason,created_by) VALUES (?,?,?,?,?,?,?)')->execute([$company,$employee,$day,$kind,'pending','Ocorrência identificada automaticamente pelo processamento.',$user]);}
}
