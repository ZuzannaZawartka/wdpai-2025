#!/bin/bash

# Usage: ./api_tests.sh <BASE_URL>

BASE_URL=${1:-"http://localhost:8080"}

echo "Test 1: GET /login"
curl -s -L "$BASE_URL/login" | grep -Ei "FindRival|email" > /dev/null && echo "Result: PASS" || echo "Result: FAIL"

echo "Test 2: POST /login (Invalid)"
HTML=$(curl -s -c jar.txt "$BASE_URL/login")
TOKEN=$(echo "$HTML" | tr -d '\n\r' | sed -n 's/.*name="csrf_token"[^>]*value="\([^"]*\)".*/\1/p')
RESPONSE=$(curl -s -L -b jar.txt -d "email=wrong@user.com" -d "password=wrongpass" -d "csrf_token=$TOKEN" "$BASE_URL/login")

if echo "$RESPONSE" | grep -Ei "Email|hasło" > /dev/null; then
    echo "Result: PASS"
else
    echo "Result: FAIL"
fi

echo "Test 3: POST /login (Admin)"
HTML=$(curl -s -c jar.txt "$BASE_URL/login")
TOKEN=$(echo "$HTML" | tr -d '\n\r' | sed -n 's/.*name="csrf_token"[^>]*value="\([^"]*\)".*/\1/p')
STATUS=$(curl -s -o /dev/null -w "%{http_code}" -b jar.txt -d "email=admin@gmail.com" -d "password=adminadmin" -d "csrf_token=$TOKEN" "$BASE_URL/login")

if [ "$STATUS" == "303" ] || [ "$STATUS" == "200" ]; then
    echo "Result: PASS ($STATUS)"
else
    echo "Result: FAIL ($STATUS)"
fi

echo "Test 4: GET /register"
curl -s -L "$BASE_URL/register" | grep -Ei "Register|account" > /dev/null && echo "Result: PASS" || echo "Result: FAIL"

rm -f jar.txt
