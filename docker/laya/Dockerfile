# The Laya decision model (https://pypi.org/project/laya/) that
# modules/laya/Laya_client.php calls at LAYA_URL. Published by
# .github/workflows/laya-image.yml as ghcr.io/sasin91/recruiter-laya and run
# as a Trongate.cloud project service named "laya" (port 8080, 3072 MB), which
# gives the app LAYA_URL on its next deploy.
#
# The English checkpoint is downloaded at build time at its reviewed commit
# (LAYA_REVISION=reviewed) and the container runs offline: the pod needs no
# route to Hugging Face, and every start loads the same weights.
FROM python:3.12-slim-bookworm

ENV PYTHONDONTWRITEBYTECODE=1 \
    PIP_NO_CACHE_DIR=1 \
    PIP_DISABLE_PIP_VERSION_CHECK=1 \
    HF_HOME=/opt/laya/hf \
    LAYA_REVISION=reviewed

# CPU-only torch first, so laya's torch requirement doesn't pull the CUDA build
RUN pip install --index-url https://download.pytorch.org/whl/cpu torch \
    && pip install "laya[serve]==0.4.0"

# One real decision, so everything the English checkpoint needs is cached
RUN python -c "import laya; agent = laya.load('convaiinnovations/laya', device='cpu'); print(agent.predict('Job: Test. The candidate meets 1 of 1 required qualifications. Missing requirements: none.', {'meets': {'type': 'noul', 'instructions': 'Does the candidate meet every required qualification for the job?'}})['answers'])" \
    && chmod -R a+rX /opt/laya

# The pod runs it as uid 1000 with /tmp as its only writable directory
ENV HF_HUB_OFFLINE=1 \
    TRANSFORMERS_OFFLINE=1 \
    LAYA_MODELS=english \
    LAYA_DEFAULT_MODEL=english \
    LAYA_DEVICE=cpu \
    LAYA_MAX_LOADED=1 \
    LAYA_THREADS=2 \
    LAYA_HOST=0.0.0.0 \
    LAYA_PORT=8080 \
    HOME=/tmp

USER 1000

EXPOSE 8080

CMD ["python", "-m", "laya.serve"]
