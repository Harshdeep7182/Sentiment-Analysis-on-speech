import torch
import librosa
import tkinter as tk
from tkinter import filedialog
from transformers import AutoFeatureExtractor, AutoModelForAudioClassification

MODEL_DIR = "./final_wavlm_emotion_model"

DEVICE = "cuda" if torch.cuda.is_available() else "cpu"
TARGET_SR = 16000
MAX_LEN = TARGET_SR * 4

print("Running on:", DEVICE)

# ===== File upload dialog =====
root = tk.Tk()
root.withdraw()  # hide window

AUDIO_PATH = filedialog.askopenfilename(
    title="Select an audio file", filetypes=[("Audio Files", "*.wav *.mp3 *.flac")]
)

if not AUDIO_PATH:
    print("No audio selected. Exiting.")
    exit()

print("Selected audio:", AUDIO_PATH)

# ===== Load model =====
feature_extractor = AutoFeatureExtractor.from_pretrained(MODEL_DIR)

model = AutoModelForAudioClassification.from_pretrained(
    MODEL_DIR, torch_dtype=torch.float16 if DEVICE == "cuda" else torch.float32
).to(DEVICE)

model.eval()


def load_audio(path):
    audio, _ = librosa.load(path, sr=TARGET_SR)
    return audio[:MAX_LEN]


def predict(audio_path):
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

    print("\nPrediction:")
    for i, p in enumerate(probs):
        print(f"{labels[i]:8s}: {p.item():.4f}")

    print("\nFinal Emotion:", labels[int(torch.argmax(probs))])


predict(AUDIO_PATH)
