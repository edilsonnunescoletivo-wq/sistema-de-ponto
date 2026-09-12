<?php
declare(strict_types=1);

final class AfdImporter
{
    public function import(string $file, int $companyId, int $userId): array
    {
        if (!is_file($file) || filesize($file) > 10 * 1024 * 1024) throw new RuntimeException('Arquivo inválido ou maior que 10 MB.');
        $summary=['read'=>0,'imported'=>0,'duplicates'=>0,'unknown'=>0,'invalid'=>0];
        $pdo=db(); $pdo->beginTransaction();
        try {
            $batch=$pdo->prepare('INSERT INTO import_batches (company_id,file_name,file_hash,status,created_by) VALUES (?,?,?,?,?)');
            $batch->execute([$companyId,basename($_FILES['afd']['name']??'afd.txt'),hash_file('sha256',$file),'processing',$userId]);
            $batchId=(int)$pdo->lastInsertId();
            $handle=fopen($file,'rb');
            while(($line=fgets($handle))!==false){$summary['read']++;$record=$this->parse(trim($line));if(!$record){$summary['invalid']++;continue;}
                $employee=$this->employee($companyId,$record['identifier']);if(!$employee){$summary['unknown']++;continue;}
                $hash=hash('sha256',$companyId.'|'.$employee['id'].'|'.$record['at'].'|'.$record['nsr']);
                $sql=env('DB_DRIVER','sqlite')==='mysql'?'INSERT IGNORE INTO punches (company_id,employee_id,punched_at,source,nsr,original_hash) VALUES (?,?,?,?,?,?)':'INSERT OR IGNORE INTO punches (company_id,employee_id,punched_at,source,nsr,original_hash) VALUES (?,?,?,?,?,?)';
                $stmt=$pdo->prepare($sql);$stmt->execute([$companyId,$employee['id'],$record['at'],'import',$record['nsr'],$hash]);
                $stmt->rowCount()?$summary['imported']++:$summary['duplicates']++;
            }
            fclose($handle);$pdo->prepare("UPDATE import_batches SET status='completed',total_records=?,imported_records=?,rejected_records=?,completed_at=CURRENT_TIMESTAMP WHERE id=?")->execute([$summary['read'],$summary['imported'],$summary['unknown']+$summary['invalid'],$batchId]);
            $pdo->commit();return$summary;
        }catch(Throwable $e){if($pdo->inTransaction())$pdo->rollBack();throw$e;}
    }

    private function parse(string $line):?array
    {
        if($line===''||str_starts_with(strtolower($line),'nsr'))return null;
        $parts=str_getcsv($line,str_contains($line,';')?';':',');
        if(count($parts)>=3&&preg_match('/^\d{4}-\d{2}-\d{2}[ T]\d{2}:\d{2}/',$parts[1]))return['nsr'=>trim($parts[0]),'at'=>date('Y-m-d H:i:s',strtotime($parts[1])),'identifier'=>preg_replace('/\D/','',$parts[2])];
        if(preg_match('/^(\d{9})3(\d{2})(\d{2})(\d{4})(\d{2})(\d{2})(\d{11,12})/',$line,$m))return['nsr'=>$m[1],'at'=>"{$m[4]}-{$m[3]}-{$m[2]} {$m[5]}:{$m[6]}:00",'identifier'=>$m[7]];
        return null;
    }
    private function employee(int $companyId,string $identifier):array|false
    {$digits=preg_replace('/\D/','',$identifier);$s=db()->prepare("SELECT * FROM employees WHERE company_id=? AND (REPLACE(REPLACE(REPLACE(cpf,'.',''),'-',''),'/','')=? OR registration=?) LIMIT 1");$s->execute([$companyId,$digits,$identifier]);return$s->fetch();}
}
