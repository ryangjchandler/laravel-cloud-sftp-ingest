# SFTP uploads on Laravel Cloud

This app lets SFTP clients upload files to a Laravel Cloud object storage bucket. SFTPGo and ngrok run on a Worker cluster, while the App cluster serves the file browser and receives SFTP activity events.

## Cloud setup

1. Attach a private object storage bucket as the `private` disk and attach a managed database to the environment. Cloud supplies the bucket configuration; no separate S3 credentials are needed.
2. In the environment's **Build Commands**, add `sh cloud-sftp-build.sh` after Composer installation. This installs SFTPGo and ngrok for the deployed application.
3. On the environment's infrastructure canvas, add a Worker cluster. Set its autoscaling to **None** (one instance) and configure it to **stay awake** when the App cluster scales to zero.
4. In the Worker cluster's **Background processes**, add two **Custom** processes with one process each: `sh cloud-sftp-server.sh` and `sh cloud-sftp-tunnel.sh`. Both then run on the same instance.
5. Add these variables in the environment's settings:

   | Variable | Purpose |
   | --- | --- |
   | `SFTP_USERNAME`, `SFTP_PASSWORD` | SFTP login and `/files` browser login |
   | `SFTP_SSH_HOST_KEY_BASE64` | Base64-encoded Ed25519 private host key |
   | `NGROK_AUTHTOKEN` | Token from your ngrok account |
   | `SFTP_EVENT_WEBHOOK_URL` | Public HTTPS app URL followed by `/api/sftp/events` |
   | `SFTP_EVENT_WEBHOOK_TOKEN` | Random shared secret for event notifications |

   Generate the host key once with `ssh-keygen -t ed25519 -f sftp_host_key -N ''`, then set `SFTP_SSH_HOST_KEY_BASE64` to the output of `base64 < sftp_host_key | tr -d '\n'`. Keep the private key and all secret values out of the repository. The event URL and token are optional as a pair; set both to enable activity history.

6. Set the environment's Deploy Commands to include `php artisan migrate --force`, then deploy. Redeploy after changing attached resources, variables, or cluster settings.

## Connect and check uploads

Find the public `tcp://host:port` address in the ngrok background process logs. In an SFTP client, use `host` as the server, `port` as the port, and the configured SFTP username and password. For example:

```sh
sftp -P <port> <username>@<host>
```

Open `/files` on the application domain and sign in with the same username and password. The page lists files in the bucket and, when event notifications are enabled, the 30 most recent uploads, deletes, renames, and folder changes. The database keeps this history after files are deleted or workers restart.

`SFTP_PORT` defaults to `2222` for communication between SFTPGo and ngrok. `SFTP_CLOUD_DISK` defaults to `private`.

## Stable connection address

For a fixed public host and port, reserve a TCP address on a paid [ngrok plan](https://ngrok.com/pricing) and set `NGROK_TCP_ADDRESS` to the assigned `host:port`. Alternatively, [Tailscale](https://tailscale.com/docs/features/tailscale-serve) provides a stable private address when each SFTP client joins the same tailnet; that option requires replacing the ngrok tunnel process. Tailscale Funnel is not a direct public SFTP replacement because [Funnel requires TLS](https://tailscale.com/docs/features/tailscale-funnel), while standard SFTP uses SSH.
