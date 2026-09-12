<?php
declare(strict_types=1);

final class TimeCalculator
{
    public static function analyseDay(array $punches, array $schedule, int $tolerance = 10): array
    {
        sort($punches);
        $result=['worked_minutes'=>0,'expected_minutes'=>0,'overtime_minutes'=>0,'delay_minutes'=>0,'night_minutes'=>0,'issues'=>[]];
        if (count($punches)%2!==0) $result['issues'][]='missing_punch';
        for($i=0;$i+1<count($punches);$i+=2){
            $start=new DateTimeImmutable($punches[$i]); $end=new DateTimeImmutable($punches[$i+1]);
            if($end<$start)$end=$end->modify('+1 day');
            $result['worked_minutes']+=(int)(($end->getTimestamp()-$start->getTimestamp())/60);
            $result['night_minutes']+=self::nightOverlap($start,$end,$schedule['night_start']??'22:00',$schedule['night_end']??'05:00');
        }
        $break=self::between($schedule['break_start']??null,$schedule['break_end']??null);
        $result['expected_minutes']=max(0,self::between($schedule['work_start']??'08:00',$schedule['work_end']??'17:00')-$break);
        if($punches){$delay=max(0,(int)((strtotime(date('H:i',strtotime($punches[0])))-strtotime($schedule['work_start']??'08:00'))/60));$result['delay_minutes']=$delay<=$tolerance?0:$delay;if($result['delay_minutes'])$result['issues'][]='delay';}
        else $result['issues'][]='absence';
        $result['overtime_minutes']=max(0,$result['worked_minutes']-$result['expected_minutes']);
        return $result;
    }
    private static function between(?string $a,?string $b):int{return(!$a||!$b)?0:max(0,(int)((strtotime($b)-strtotime($a))/60));}
    private static function nightOverlap(DateTimeImmutable $start,DateTimeImmutable $end,string $nightStart,string $nightEnd):int{
        $total=0;$day=$start->setTime(0,0)->modify('-1 day');while($day<=$end){$from=new DateTimeImmutable($day->format('Y-m-d').' '.$nightStart);$to=new DateTimeImmutable($day->format('Y-m-d').' '.$nightEnd);if($to<=$from)$to=$to->modify('+1 day');$total+=(int)(max(0,min($end->getTimestamp(),$to->getTimestamp())-max($start->getTimestamp(),$from->getTimestamp()))/60);$day=$day->modify('+1 day');}return$total;
    }
}
