# -*- coding: utf-8 -*-
"""new_emotion_optimize.ipynb"""

# =========================
# 1. ENVIRONMENT SETUP
# =========================
!pip uninstall -y transformers accelerate datasets peft
!pip install -q \
  transformers==4.36.2 \
  accelerate==0.26.1 \
  datasets==2.16.1 \
  peft==0.7.1 \
  soundfile \
  kaggle \
  librosa

import torch, transformers, accelerate, datasets
print("Torch:", torch.__version__)
print("Transformers:", transformers.__version__)
print("Accelerate:", accelerate.__version__)
print("CUDA:", torch.cuda.is_available())

# =========================
# 2. KAGGLE SETUP (KEY ALREADY PRESENT)
# =========================
!mkdir -p /content/data

# =========================
# 3. DOWNLOAD DATASETS
# =========================
!kaggle datasets download -d uwrfkaggler/ravdess-emotional-speech-audio -p /content/data
!kaggle datasets download -d ejlok1/toronto-emotional-speech-set-tess -p /content/data
!kaggle datasets download -d ejlok1/cremad -p /content/data

!unzip -q /content/data/ravdess-emotional-speech-audio.zip -d /content/data/ravdess
!unzip -q /content/data/toronto-emotional-speech-set-tess.zip -d /content/data/tess
!unzip -q /content/data/cremad.zip -d /content/data/cremad

!mv "/content/data/tess/TESS Toronto emotional speech set data"/* /content/data/tess/
!rm -rf "/content/data/tess/TESS Toronto emotional speech set data"

# =========================
# 4. BUILD DATAFRAME
# =========================
import os, pandas as pd

EMOTIONS = ["angry","disgust","fear","happy","neutral","sad"]

def extract(base, name):
    rows=[]
    for r,_,fs in os.walk(base):
        for f in fs:
            if not f.endswith(".wav"):
                continue
            fl=f.lower()
            lbl=None
            if name=="ravdess":
                p=f.split("-")
                if len(p)==7:
                    lbl={"01":"neutral","02":"neutral","03":"happy",
                         "04":"sad","05":"angry","06":"fear","07":"disgust"}.get(p[2])
            elif name=="tess":
                for e in EMOTIONS:
                    if e in r.lower():
                        lbl=e
            elif name=="cremad":
                if "_ang_" in fl: lbl="angry"
                elif "_dis_" in fl: lbl="disgust"
                elif "_fea_" in fl: lbl="fear"
                elif "_hap_" in fl: lbl="happy"
                elif "_neu_" in fl: lbl="neutral"
                elif "_sad_" in fl: lbl="sad"
            if lbl:
                rows.append({"path":os.path.join(r,f),"label":lbl})
    return pd.DataFrame(rows)

df = pd.concat([
    extract("/content/data/ravdess","ravdess"),
    extract("/content/data/tess","tess"),
    extract("/content/data/cremad","cremad")
], ignore_index=True)

label2id = {l:i for i,l in enumerate(EMOTIONS)}
id2label = {i:l for l,i in label2id.items()}
df["label_id"] = df["label"].map(label2id)

print(df["label"].value_counts())

# =========================
# 5. DATASET + PREPROCESSING
# =========================
from datasets import Dataset, Audio
from transformers import AutoFeatureExtractor

dataset = Dataset.from_pandas(df)
dataset = dataset.cast_column("path", Audio(sampling_rate=16000))

MODEL_NAME = "microsoft/wavlm-base"
feature_extractor = AutoFeatureExtractor.from_pretrained(MODEL_NAME)

MAX_LEN = 16000 * 4  

def preprocess(x):
    audio = x["path"]["array"][:MAX_LEN]
    out = feature_extractor(
        audio,
        sampling_rate=16000,
        truncation=True,
        max_length=MAX_LEN
    )
    return {
        "input_values": out["input_values"][0],
        "label": x["label_id"]
    }

dataset = dataset.map(
    preprocess,
    remove_columns=dataset.column_names,
    num_proc=1
)

split = dataset.train_test_split(test_size=0.2, seed=42)

# =========================
# 6. MODEL + PARTIAL UNFREEZE
# =========================
from transformers import AutoModelForAudioClassification

model = AutoModelForAudioClassification.from_pretrained(
    MODEL_NAME,
    num_labels=6,
    label2id=label2id,
    id2label=id2label
)

model.gradient_checkpointing_enable()

# Freeze all layers
for p in model.wavlm.parameters():
    p.requires_grad = False

# 🔥 Unfreeze last 4 encoder layers
for layer in model.wavlm.encoder.layers[-4:]:
    for p in layer.parameters():
        p.requires_grad = True

# =========================
# 7. TRAINING ARGUMENTS
# =========================
from transformers import TrainingArguments

training_args = TrainingArguments(
    output_dir="/content/final_wavlm_emotion_model",
    evaluation_strategy="epoch",
    save_strategy="epoch",
    per_device_train_batch_size=2,   
    per_device_eval_batch_size=2,
    gradient_accumulation_steps=4,    
    num_train_epochs=10,             
    learning_rate=1e-5,               
    fp16=True,
    logging_steps=50,
    report_to="none",
    dataloader_num_workers=2
)

# =========================
# 8. TRAINER
# =========================
from transformers import Trainer, DataCollatorWithPadding

trainer = Trainer(
    model=model,
    args=training_args,
    train_dataset=split["train"],
    eval_dataset=split["test"],
    tokenizer=feature_extractor,
    data_collator=DataCollatorWithPadding(feature_extractor),
)

import gc
gc.collect()
torch.cuda.empty_cache()

trainer.train()

# =========================
# 9. SAVE FINAL MODEL
# =========================
trainer.save_model("/content/final_wavlm_emotion_model")
feature_extractor.save_pretrained("/content/final_wavlm_emotion_model")