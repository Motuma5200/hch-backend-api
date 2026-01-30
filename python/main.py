from fastapi import FastAPI
from fastapi.middleware.cors import CORSMiddleware
from pydantic import BaseModel
from llama_cpp import Llama
import uvicorn

app = FastAPI(title="HealthCheckHub Medical AI API")

app.add_middleware(
    CORSMiddleware,
    allow_origins=["*"],           # ← Change to your frontend URL in production!
    allow_credentials=True,
    allow_methods=["*"],
    allow_headers=["*"],
)

print("Loading BioMistral-7B (medical fine-tuned model)... This may take 30-90 seconds.")

# ── CHANGE THIS PATH to where you actually saved the file ──
MODEL_PATH = "BioMistral-7B.Q4_K_M.gguf"          # ← most common name
# MODEL_PATH = "./BioMistral-7B/BioMistral-7B-GGUF.Q4_K_M.gguf"   # if you used a subfolder
# MODEL_PATH = "C:/Users/YourName/Downloads/BioMistral-7B-GGUF.Q4_K_M.gguf"  # Windows example

llm = Llama(
    model_path=MODEL_PATH,
    n_ctx=4096,           # 4096 is usually fine; reduce to 2048/3072 if you get OOM
    n_threads=6,          # adjust to ≈ your CPU cores - 2
    n_gpu_layers=0,       # 0 = CPU only • change to -1 or 30-40 if you have GPU + enough VRAM
    verbose=False
)

print("BioMistral-7B medical model loaded! Ready for health-related questions.")

class Query(BaseModel):
    question: str

@app.get("/")
def home():
    return {"message": "Medical AI API (BioMistral-7B) is running! POST to /generate"}

@app.post("/generate")
def generate_response(query: Query):
    user_question = query.question.strip()

    # ── BioMistral / Mistral-Instruct style system prompt ──
    system_prompt = (
        "You are a helpful, kind and experienced doctor specialized in preventive medicine. "
        "Answer in clear, simple language. Use bullet points when giving advice. "
        "Be empathetic and supportive. "
        "Always finish your answer with this exact sentence:\n"
        "'This is general health information only – not a medical diagnosis or treatment. "
        "Please consult a qualified healthcare professional.'"
    )

    # Mistral-Instruct / BioMistral chat template
    prompt = f"""[INST] {system_prompt}

{user_question} [/INST]"""

    output = llm(
        prompt,
        max_tokens=512,
        temperature=0.65,       # slightly lower than before → more focused answers
        top_p=0.9,
        repeat_penalty=1.1,
        stop=["</s>", "[INST]", "[/INST]"],
        echo=False
    )

    response_text = output["choices"][0]["text"].strip()

    return {"response": response_text}

if __name__ == "__main__":
    print("Starting server on http://127.0.0.1:8080")
    uvicorn.run(app, host="127.0.0.1", port=8080)