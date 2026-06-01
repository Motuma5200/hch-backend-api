from fastapi import FastAPI, HTTPException
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
import httpx  
import uvicorn

app = FastAPI(title="HealthCheckHub Medical AI API")

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],           
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

HF_ACCESS_TOKEN = "h" 

print("FastAPI configured to route queries through Hugging Face Cloud APIs!")

class Query(BaseModel):
    question: str

@app.get("/")
def home():
    return {"message": "Medical AI API (Hugging Face Cloud BioMistral) is running! POST to /generate"}

@app.post("/generate")
async def generate_response(query: Query):
    user_question = query.question.strip()

    system_prompt = (
        "You are a helpful, kind and experienced doctor specialized in preventive medicine. "
        "Answer in clear, simple language. Use bullet points when giving advice. "
        "Be empathetic and supportive. "
        "Always finish your answer with this exact sentence:\n"
        "'This is general health information only – not a medical diagnosis or treatment. "
        "Please consult a qualified healthcare professional.'"
    )

    # Payload structured in standard ChatCompletions format
    payload = {
        "model": "BioMistral/BioMistral-7B-GGUF",
        "messages": [
            {"role": "system", "content": system_prompt},
            {"role": "user", "content": user_question}
        ],
        "max_tokens": 512,
        "temperature": 0.65,
        "top_p": 0.9
    }

    # Hugging Face cloud validation router
    API_URL = "https://router.huggingface.co/v1/chat/completions"
    headers = {
        "Authorization": f"Bearer {HF_ACCESS_TOKEN}",
        "Content-Type": "application/json"
    }
    
    async with httpx.AsyncClient(timeout=60.0) as client:
        try:
            response = await client.post(API_URL, json=payload, headers=headers)
            
            # Handle 503 errors (when Hugging Face is spinning up/warming up the model cache)
            if response.status_code == 503:
                return {
                    "response": (
                        "The cloud medical model is currently waking up on Hugging Face's serverless cluster. "
                        "Please re-submit your question in 15 to 20 seconds!\n\n"
                        "This is general health information only – not a medical diagnosis or treatment. "
                        "Please consult a qualified healthcare professional."
                    )
                }
            
            if response.status_code != 200:
                raise HTTPException(
                    status_code=response.status_code, 
                    detail=f"Hugging Face server returned an error: {response.text}"
                )
                
            data = response.json()
            response_text = data["choices"][0]["message"]["content"].strip()
            return {"response": response_text}
            
        except httpx.RequestError as exc:
            raise HTTPException(
                status_code=500, 
                detail=f"An error occurred while connecting to the AI cloud network: {exc}"
            )

if __name__ == "__main__":
    # Serves on 0.0.0.0 so your mobile phone hotspot configuration can view it seamlessly
    print("Starting server on http://0.0.0.0:8080")
    uvicorn.run(app, host="0.0.0.0", port=8080)