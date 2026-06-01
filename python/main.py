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

# Your valid token is safe here
HF_ACCESS_TOKEN = "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" 

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

    # Combine system prompt and user question manually for the standard text-generation endpoint
    full_prompt = f"<s>[INST] {system_prompt}\n\nUser Question: {user_question} [/INST]"

    payload = {
        "inputs": full_prompt,
        "parameters": {
            "max_new_tokens": 512,
            "temperature": 0.65,
            "top_p": 0.9,
            "return_full_text": False
        }
    }

    # CORRECT ENDPOINT: Points directly to the serverless model repository
    API_URL = "https://api-inference.huggingface.co/models/BioMistral/BioMistral-7B"
    
    headers = {
        "Authorization": f"Bearer {HF_ACCESS_TOKEN}",
        "Content-Type": "application/json"
    }
    
    async with httpx.AsyncClient(timeout=60.0) as client:
        try:
            response = await client.post(API_URL, json=payload, headers=headers)
            
            # Handle 503 errors (Model warming up / loading into memory)
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
            
            # The standard Inference API returns a list containing a dict with 'generated_text'
            if isinstance(data, list) and len(data) > 0:
                response_text = data[0].get("generated_text", "").strip()
            else:
                response_text = "Error parsing response layout from Hugging Face."
                
            return {"response": response_text}
            
        except httpx.RequestError as exc:
            raise HTTPException(
                status_code=500, 
                detail=f"An error occurred while connecting to the AI cloud network: {exc}"
            )

if __name__ == "__main__":
    print("Starting server on http://0.0.0.0:8080")
    uvicorn.run(app, host="0.0.0.0", port=8080)