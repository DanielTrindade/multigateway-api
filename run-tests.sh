#!/bin/bash

# Cores para formatação
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[0;33m'
BLUE='\033[0;34m'
NC='\033[0m' # No Color

echo -e "${BLUE}=== EXECUTANDO TESTES COM BANCO DE DADOS PRÉ-POPULADO ===${NC}\n"

# Determine o comando do Docker Compose
if command -v docker-compose &> /dev/null; then
    DOCKER_COMPOSE="docker-compose"
elif command -v docker &> /dev/null && docker compose version &> /dev/null; then
    DOCKER_COMPOSE="docker compose"
else
    echo -e "${RED}Docker Compose não encontrado. Por favor, instale o Docker Compose.${NC}"
    exit 1
fi

# Definir nomes dos serviços
APP_SERVICE="app"
DB_TEST_SERVICE="db_test"

# Verificar se o arquivo .env.testing existe
if [ ! -f ".env.testing" ]; then
    echo -e "${RED}[ERRO]${NC} Arquivo .env.testing não encontrado!"
    exit 1
fi

# Verificar se os contêineres estão rodando
echo -e "${BLUE}[INFO]${NC} Verificando estado dos contêineres..."
if ! $DOCKER_COMPOSE ps --services --filter "status=running" | grep -q "$APP_SERVICE"; then
    echo -e "${YELLOW}[AVISO]${NC} Contêiner da aplicação não está em execução. Iniciando..."
    $DOCKER_COMPOSE up -d
    echo -e "${GREEN}[SUCCESS]${NC} Contêineres iniciados."
    sleep 5
fi

# Configurar o banco de testes
echo -e "${BLUE}[INFO]${NC} Configurando banco de dados de teste..."

# Definir variáveis do banco de teste
DB_TEST_HOST="db_test"
DB_TEST_DATABASE="multigateway_test"
DB_TEST_USERNAME="multigateway_test"
DB_TEST_PASSWORD="test_password"

# Garantir que o banco de teste existe
$DOCKER_COMPOSE exec $DB_TEST_SERVICE mysql -u root -proot_password -e "CREATE DATABASE IF NOT EXISTS $DB_TEST_DATABASE;"
$DOCKER_COMPOSE exec $DB_TEST_SERVICE mysql -u root -proot_password -e "CREATE USER IF NOT EXISTS '$DB_TEST_USERNAME'@'%' IDENTIFIED BY '$DB_TEST_PASSWORD';"
$DOCKER_COMPOSE exec $DB_TEST_SERVICE mysql -u root -proot_password -e "GRANT ALL PRIVILEGES ON $DB_TEST_DATABASE.* TO '$DB_TEST_USERNAME'@'%';"
$DOCKER_COMPOSE exec $DB_TEST_SERVICE mysql -u root -proot_password -e "FLUSH PRIVILEGES;"

# Limpar caches e configurações
echo -e "${BLUE}[INFO]${NC} Limpando caches e preparando ambiente de teste..."
$DOCKER_COMPOSE exec $APP_SERVICE php artisan config:clear
$DOCKER_COMPOSE exec $APP_SERVICE php artisan route:clear
$DOCKER_COMPOSE exec $APP_SERVICE php artisan cache:clear

# Executar migrações e seeders no banco de teste
echo -e "${BLUE}[INFO]${NC} Executando migrações e seeders no banco de teste..."
$DOCKER_COMPOSE exec -e DB_CONNECTION=mysql -e DB_HOST=$DB_TEST_HOST -e DB_DATABASE=$DB_TEST_DATABASE -e DB_USERNAME=$DB_TEST_USERNAME -e DB_PASSWORD=$DB_TEST_PASSWORD $APP_SERVICE php artisan migrate:fresh --seed --env=testing

echo -e "${GREEN}[SUCCESS]${NC} Banco de testes preparado com dados de seed!"

# Definir variável de ambiente para sinalizar que os testes devem usar dados de seed
export RUN_SEEDS_FOR_TESTS=true

# Executar os testes
echo -e "\n${BLUE}[INFO]${NC} Executando testes..."
$DOCKER_COMPOSE exec -e DB_CONNECTION=mysql -e DB_HOST=$DB_TEST_HOST -e DB_DATABASE=$DB_TEST_DATABASE -e DB_USERNAME=$DB_TEST_USERNAME -e DB_PASSWORD=$DB_TEST_PASSWORD -e RUN_SEEDS_FOR_TESTS=true $APP_SERVICE php artisan test $@

# Capturar o código de resultado
TEST_RESULT=$?

# Verificar resultado
if [ $TEST_RESULT -eq 0 ]; then
    echo -e "\n${GREEN}[SUCCESS]${NC} Todos os testes passaram com sucesso!"
else
    echo -e "\n${RED}[ERROR]${NC} Alguns testes falharam. Verifique os logs acima."
fi

echo -e "\n${BLUE}=== FIM DOS TESTES COM BANCO PRÉ-POPULADO ===${NC}"
exit $TEST_RESULT