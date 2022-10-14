<?php

class ConstructionStages
{
    private $db;
    private $errorMsg = "";

    public function __construct()
    {
        $this->db = Api::getDb();
    }

    public function validateData(&$data){
        if($data->name && strlen($data->name) > 255){
            $this->errorMsg = ["error" => ["code" => 400 ,"message" => "name should be maximum 255 chars"]];
            return false;}

        if($data->startDate && !$this->checkValidIso8601($data->startDate)){
            $this->errorMsg = ["error" => ["code" => 400 ,"message" => "startDate should match iso8601 format"]];
            return false;}

        if($data->endDate && (!$data->startDate || !$this->checkValidIso8601($data->endDate) ||  $data->startDate > $data->endDate)){
            $this->errorMsg = ["error" => ["code" => 400 ,"message" => "endDate should match iso8601 format and should be greater than startDate"]];
            return false;}

        if($data->durationUnit && !in_array($data->durationUnit, ["HOURS","DAYS","WEEKS"]))
            $data->durationUnit = "DAYS";

        if($data->color && !preg_match('/^#[a-f0-9]{6}$/i', $data->color)){
            $this->errorMsg = ["error" => ["code" => 400 ,"message" => "color should match #FF0000 style"]];
            return false;}

        if($data->externalId && strlen($data->externalId) > 255){
            $this->errorMsg = ["error" => ["code" => 400 ,"message" => "externalId should be maximum 255 chars"]];
            return false;}

        if($data->status && !in_array($data->status, ["NEW","PLANNED","DELETED"]))
            $data->status = "NEW";

        return true;
    }

    //Fetch CS array of objects
    public function getAll()
    {
        $stmt = $this->db->prepare("
			SELECT
				ID as id,
				name, 
				strftime('%Y-%m-%dT%H:%M:%SZ', start_date) as startDate,
				strftime('%Y-%m-%dT%H:%M:%SZ', end_date) as endDate,
				duration,
				durationUnit,
				color,
				externalId,
				status
			FROM construction_stages WHERE status != 'DELETED'
		");
        $stmt->execute();
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    //Fetch CS single object
    public function getSingle($id)
    {
        if(!$id)
            return ["error" => ["code" => 400 ,"message" => "id is missing"]];

        $stmt = $this->db->prepare("
			SELECT
				ID as id,
				name, 
				strftime('%Y-%m-%dT%H:%M:%SZ', start_date) as startDate,
				strftime('%Y-%m-%dT%H:%M:%SZ', end_date) as endDate,
				duration,
				durationUnit,
				color,
				externalId,
				status
			FROM construction_stages
			WHERE ID = :id AND status != 'DELETED'
		");
        $stmt->execute(['id' => $id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    //Create CS and return inserted object
    public function post(ConstructionStagesCreate $data)
    {
        if(!$this->validateData($data))
            return $this->errorMsg;

        $data->duration = $this->generateDuration($data);

        $stmt = $this->db->prepare("
			INSERT INTO construction_stages
			    (name, start_date, end_date, duration, durationUnit, color, externalId, status)
			    VALUES (:name, :start_date, :end_date, :duration, :durationUnit, :color, :externalId, :status)
			");
        $stmt->execute([
            'name' => $data->name,
            'start_date' => $data->startDate,
            'end_date' => $data->endDate,
            'duration' => $data->duration,
            'durationUnit' => $data->durationUnit,
            'color' => $data->color,
            'externalId' => $data->externalId,
            'status' => $data->status ? : "NEW",
        ]);
        return $this->getSingle($this->db->lastInsertId());
    }

    //Update CS fields passed by User
    public function patchConstructionStages(ConstructionStagesPatch $data)
    {
        if(!$data->id)
            return ["error" => ["code" => 400  ,"message" => "id is missing"]];

        if(!$this->validateData($data))
            return $this->errorMsg;

        $data->duration = $this->generateDuration($data);

        $fields = array(
            "name" => $data->name,
            "start_date" => $data->startDate,
            "end_date" => $data->endDate,
            "duration" => $data->duration,
            "durationUnit" => $data->durationUnit,
            "color" => $data->color,
            "externalId" => $data->externalId,
            "status" => $data->status
        );

        $fieldsToSet = [];
        foreach ($fields as $key => $value){
            if($value != null)
                $fieldsToSet[] = $key . " = '$value'";
        }

        $query = "UPDATE construction_stages SET ". implode(", ",$fieldsToSet) . " WHERE ID = $data->id";

        $this->db->exec($query);

        return $this->getSingle($data->id)[0];
    }

    //Set status = 'DELETED' on CS
    public function deleteConstructionStage($id){
        $this->db->exec("UPDATE construction_stages SET status = 'DELETED' WHERE id = $id");
        return ['deleted' => ['id' => $id]];
    }

    private function generateDuration($data){
        if(!$data->endDate || !$data->startDate)
            return null;

        $date1 = new DateTime($data->endDate);
        $date2 = new DateTime($data->startDate);
        $interval = $date1->diff($date2);

        if($data->durationUnit == "HOURS")
            return $interval->days*24 + $interval->h;
        if($data->durationUnit == "DAYS")
            return $interval->days;
        if($data->durationUnit == "WEEKS")
            return $interval->days/7;
    }

    private function checkValidIso8601($field){
        if (preg_match('/^[12]\d{3}(?:-\d{2}){2}T(?:(?:[01]\d)|(?:2[0-3]))(?::[0-5][0-9]){2}Z$/', $field)) {
            $date = explode('T', $field)[0];
            list($y, $m, $d) = explode('-', $date);
            return checkdate($m, $d, $y);}
    }
}