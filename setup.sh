#!/bin/bash

# Determine if we should use docker-compose or docker compose
if command -v docker-compose &> /dev/null; then
    DOCKER_COMPOSE="docker-compose"
elif command -v docker &> /dev/null && docker compose version &> /dev/null; then
    DOCKER_COMPOSE="docker compose"
else
    echo "Docker Compose não encontrado. Por favor, instale o Docker e o Docker Compose."
    exit 1
fi

echo "Usando comando: $DOCKER_COMPOSE"

# Verificar se o diretório da aplicação Laravel existe
if [ ! -d "multigateway-app" ]; then
    echo "ERRO: O diretório 'multigateway-app' não foi encontrado!"
    echo "Certifique-se de que seu projeto Laravel está no diretório 'multigateway-app'."
    exit 1
fi

# Create necessary directories
mkdir -p docker/nginx/conf.d

# Create or update nginx config
if [ ! -f "docker/nginx/conf.d/app.conf" ]; then
    echo "Criando configuração do Nginx..."
    cat > docker/nginx/conf.d/app.conf << EOL
server {
    listen 80;
    index index.php index.html;
    error_log  /var/log/nginx/error.log;
    access_log /var/log/nginx/access.log;
    root /var/www/html/public;
    location ~ \.php$ {
        try_files \$uri =404;
        fastcgi_split_path_info ^(.+\.php)(/.+)$;
        fastcgi_pass app:9000;
        fastcgi_index index.php;
        include fastcgi_params;
        fastcgi_param SCRIPT_FILENAME \$document_root\$fastcgi_script_name;
        fastcgi_param PATH_INFO \$fastcgi_path_info;
    }
    location / {
        try_files \$uri \$uri/ /index.php?\$query_string;
        gzip_static on;
    }
}
EOL
fi

# Define a função para atualizar uma linha no .env
update_env_line() {
  local key=$1
  local value=$2
  local env_file=$3

  # Verifica se a linha já existe
  if grep -q "^$key=" "$env_file"; then
    # Substitui a linha existente
    sed -i "s|^$key=.*|$key=$value|" "$env_file"
  else
    # Adiciona a linha se não existir
    echo "$key=$value" >> "$env_file"
  fi
  
  echo "Configurado $key=$value"
}

# Update .env file in the Laravel project
echo "Configurando arquivo .env da aplicação Laravel..."
if [ -f "multigateway-app/.env" ]; then
    # Update database configuration usando a função segura
    update_env_line "DB_CONNECTION" "mysql" "multigateway-app/.env"
    update_env_line "DB_HOST" "db" "multigateway-app/.env"
    update_env_line "DB_PORT" "3306" "multigateway-app/.env"
    update_env_line "DB_DATABASE" "multigateway-db" "multigateway-app/.env"
    update_env_line "DB_USERNAME" "multigateway" "multigateway-app/.env"
    update_env_line "DB_PASSWORD" "multigateway_password" "multigateway-app/.env"

    # Add gateway configurations
    update_env_line "GATEWAY1_URL" "http://gateway1:3001" "multigateway-app/.env"
    update_env_line "GATEWAY1_EMAIL" "dev@betalent.tech" "multigateway-app/.env"
    update_env_line "GATEWAY1_TOKEN" "FEC9BB078BF338F464F96B48089EB498" "multigateway-app/.env"
    update_env_line "GATEWAY2_URL" "http://gateway2:3002" "multigateway-app/.env"
    update_env_line "GATEWAY2_AUTH_TOKEN" "tk_f2198cc671b5289fa856" "multigateway-app/.env"
    update_env_line "GATEWAY2_AUTH_SECRET" "3d15e8ed6131446ea7e3456728b1211f" "multigateway-app/.env"
else
    echo "AVISO: Arquivo .env não encontrado em multigateway-app/.env"
    echo "Certifique-se de que seu projeto Laravel está configurado corretamente."
    
    if [ -f "multigateway-app/.env.example" ]; then
        echo "Criando arquivo .env a partir do .env.example..."
        cp multigateway-app/.env.example multigateway-app/.env
        
        # Configurar o .env usando a função segura
        update_env_line "DB_CONNECTION" "mysql" "multigateway-app/.env"
        update_env_line "DB_HOST" "db" "multigateway-app/.env"
        update_env_line "DB_PORT" "3306" "multigateway-app/.env"
        update_env_line "DB_DATABASE" "multigateway-db" "multigateway-app/.env"
        update_env_line "DB_USERNAME" "multigateway" "multigateway-app/.env"
        update_env_line "DB_PASSWORD" "multigateway_password" "multigateway-app/.env"

        update_env_line "GATEWAY1_URL" "http://gateway1:3001" "multigateway-app/.env"
        update_env_line "GATEWAY1_EMAIL" "dev@betalent.tech" "multigateway-app/.env"
        update_env_line "GATEWAY1_TOKEN" "FEC9BB078BF338F464F96B48089EB498" "multigateway-app/.env"
        update_env_line "GATEWAY2_URL" "http://gateway2:3002" "multigateway-app/.env"
        update_env_line "GATEWAY2_AUTH_TOKEN" "tk_f2198cc671b5289fa856" "multigateway-app/.env"
        update_env_line "GATEWAY2_AUTH_SECRET" "3d15e8ed6131446ea7e3456728b1211f" "multigateway-app/.env"
    else
        echo "ERRO: Arquivo .env.example não encontrado."
        exit 1
    fi
fi

# Build and start the Docker containers
echo "Building and starting Docker containers..."
$DOCKER_COMPOSE build
$DOCKER_COMPOSE up -d

# Wait for the containers to be ready
echo "Waiting for containers to be ready..."
sleep 10

# Verificar se o Laravel está funcionando corretamente
echo "Verificando se o Laravel está funcionando corretamente..."
if $DOCKER_COMPOSE exec app php artisan --version &> /dev/null; then
    echo "Laravel está funcionando corretamente!"
    
    echo "Gerando chave da aplicação..."
    $DOCKER_COMPOSE exec app php artisan key:generate --force
    
    echo "Executando migrações e seeders..."
    $DOCKER_COMPOSE exec app php artisan migrate --seed
else
    echo "ERRO: Laravel não está funcionando corretamente!"
    echo "Verifique os logs para mais detalhes:"
    $DOCKER_COMPOSE logs app
fi

echo -e "\n====================================="
echo "Setup concluído!"
echo "Se tudo correu bem, sua aplicação Laravel está rodando em:"
echo "- Aplicação: http://localhost:8000"
echo "- API: http://localhost:8000/api"
echo "====================================="
echo "Para verificar o status dos containers:"
echo "$DOCKER_COMPOSE ps"
echo "====================================="
echo "Para ver os logs da aplicação:"
echo "$DOCKER_COMPOSE logs -f app"
echo "====================================="
echo "Para acessar o terminal do container:"
echo "$DOCKER_COMPOSE exec app bash"
echo "====================================="