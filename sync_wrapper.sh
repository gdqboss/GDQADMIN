#!/bin/bash
rsync -avz --delete -avz --delete -e "ssh -i /root/PEMS/3hck.pem -p 22 -o StrictHostKeyChecking=no -o PasswordAuthentication=no -o ConnectTimeout=10" /home/gdq/dist-3/ ubuntu@43.160.238.201:/home/ubuntu/dist_sync/
