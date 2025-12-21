#!/bin/bash

# Start Apache in the background
apache2-foreground &

# Wait a moment for Apache to start
sleep 2

# Start the Node.js server
node server.js
