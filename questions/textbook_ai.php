<?php

header("Content-Type: application/json");

$apiKey = getenv("HF_API_KEY");

$data = json_decode(file_get_contents("php://input"), true);
$question = $data["question"] ?? "";

if(!$question){
echo json_encode(["error"=>"No question"]);
exit;
}

$prompt = "Explain this topic like a school textbook for WAEC/JAMB students: ".$question;

$url = "https://api-inference.huggingface.co/models/google/flan-t5-large";

$payload = json_encode([
"inputs"=>$prompt
]);

$headers = [
"Authorization: Bearer ".$apiKey,
"Content-Type: application/json"
];

$ch = curl_init($url);

curl_setopt($ch, CURLOPT_RETURNTRANSFER,true);
curl_setopt($ch, CURLOPT_POST,true);
curl_setopt($ch, CURLOPT_POSTFIELDS,$payload);
curl_setopt($ch, CURLOPT_HTTPHEADER,$headers);

$response = curl_exec($ch);

curl_close($ch);

echo $response;
