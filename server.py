from fastapi import FastAPI, File, UploadFile
import uvicorn
import shutil
import os
import torch
import librosa
from transformers import AutoFeatureExtractor, AutoModelForAudioClassification

# ================= CONFIG =================
UPLOAD_DIR = "uploads"
MODEL_DIR = "./final_wavlm_emotion_model"
TARGET_SR = 16000
MAX_LEN = TARGET_SR * 4
DEVICE = "cuda" if torch.cuda.is_available() else "cpu"

os.makedirs(UPLOAD_DIR, exist_ok=True)

# ================= LOAD MODEL ON STARTUP =================
print("Loading model on:", DEVICE)

feature_extractor = AutoFeatureExtractor.from_pretrained(MODEL_DIR)

model = AutoModelForAudioClassification.from_pretrained(
    MODEL_DIR, torch_dtype=torch.float16 if DEVICE == "cuda" else torch.float32
).to(DEVICE)

model.eval()

# ================= FASTAPI =================
app = FastAPI()


def load_audio(path):
    audio, _ = librosa.load(path, sr=TARGET_SR)
    return audio[:MAX_LEN]


def predict_emotion(audio_path):
    audio = load_audio(audio_path)

    inputs = feature_extractor(
        audio,
        sampling_rate=TARGET_SR,
        return_tensors="pt",
        truncation=True,
        max_length=MAX_LEN,
    )

    inputs = {k: v.to(DEVICE) for k, v in inputs.items()}

    with torch.no_grad():
        logits = model(**inputs).logits

    probs = torch.softmax(logits, dim=-1)[0]
    labels = model.config.id2label

    prediction = labels[int(torch.argmax(probs))]

    scores = {labels[i]: float(probs[i]) for i in range(len(probs))}

    return prediction, scores


@app.post("/upload")
async def upload_file(file: UploadFile = File(...)):
    file_path = os.path.join(UPLOAD_DIR, file.filename)

    with open(file_path, "wb") as buffer:
        shutil.copyfileobj(file.file, buffer)

    sentiment, probabilities = predict_emotion(file_path)

    return {
        "status": "completed",
        "filename": file.filename,
        "sentiment": sentiment,
        "probabilities": probabilities,
    }


if __name__ == "__main__":
    uvicorn.run("server:app", host="0.0.0.0", port=9000, reload=True)
