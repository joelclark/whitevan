# minio

This should be done per host:

```yaml
services:
  minio:
    image: minio/minio:latest
    container_name: minio
    ports:
      - "9000:9000"   # API / S3 endpoint
      - "9001:9001"   # Web console
    environment:
      MINIO_ROOT_USER: minioadmin
      MINIO_ROOT_PASSWORD: minioadmin123
    volumes:
      - minio_data:/data
    command: server /data --console-address ":9001"

volumes:
  minio_data:
```

```bash
docker compose up -d minio
```

Log in and create the bucket.

Then open the console at:

http://127.0.0.1:9001

Login with `minioadmin` / `minioadmin123`
Create a bucket called `whitevan-dev`

