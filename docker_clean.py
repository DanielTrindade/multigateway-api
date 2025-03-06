#!/usr/bin/env python3
"""
Docker System Cleanup Tool
--------------------------
Script para limpar recursos Docker do projeto de pagamento multi-gateway.
Seguindo as diretrizes do PEP 8.
"""

import os
import subprocess
import sys
import shutil


# Cores para formatação
class Colors:
    """Classe para definir cores e estilos para o terminal."""
    INFO = "\033[34m"
    SUCCESS = "\033[32m"
    WARNING = "\033[33m"
    ERROR = "\033[31m"
    BOLD = "\033[1m"
    RED_BG = "\033[1;31m"
    GREEN_BG = "\033[1;42m"
    CYAN = "\033[1;36m"
    RESET = "\033[0m"


def log_info(message):
    """Exibe mensagem de informação."""
    print(f"{Colors.INFO}[INFO]{Colors.RESET} {message}")


def log_success(message):
    """Exibe mensagem de sucesso."""
    print(f"{Colors.SUCCESS}[SUCCESS]{Colors.RESET} {message}")


def log_warning(message):
    """Exibe mensagem de aviso."""
    print(f"{Colors.WARNING}[WARNING]{Colors.RESET} {message}")


def log_error(message):
    """Exibe mensagem de erro."""
    print(f"{Colors.ERROR}[ERROR]{Colors.RESET} {message}")


def run_command(command, check=True, shell=True):
    """
    Executa um comando e retorna o resultado.

    Args:
        command: Comando a ser executado
        check: Se deve verificar o código de saída do comando
        shell: Se deve usar o shell para executar o comando

    Returns:
        Objeto CompletedProcess com os resultados do comando
    """
    try:
        result = subprocess.run(
            command,
            shell=shell,
            check=check,
            text=True,
            stdout=subprocess.PIPE,
            stderr=subprocess.PIPE
        )
        return result
    except subprocess.CalledProcessError as e:
        log_error(f"Erro ao executar comando: {e}")
        return None


def check_docker_compose():
    """
    Determina qual comando do Docker Compose usar.

    Returns:
        String com o comando a ser usado (docker-compose ou docker compose)
    """
    # Verificar docker-compose
    if shutil.which("docker-compose"):
        return "docker-compose"
    # Verificar docker compose (novo formato)
    elif shutil.which("docker") and run_command("docker compose version", check=False).returncode == 0:
        return "docker compose"
    else:
        log_error(
            "Docker Compose não encontrado. Por favor, instale o Docker Compose antes de continuar.")
        sys.exit(1)


def clean_laravel_cache():
    """Limpa os arquivos de cache do Laravel."""
    log_info("Limpando arquivos de cache do Laravel...")

    # Limpar diretórios de cache do Laravel
    cache_dirs = [
        "multigateway-app/bootstrap/cache",
        "multigateway-app/storage/framework/cache/data",
        "multigateway-app/storage/framework/sessions",
        "multigateway-app/storage/framework/views"
    ]

    for directory in cache_dirs:
        if os.path.exists(directory):
            for item in os.listdir(directory):
                item_path = os.path.join(directory, item)
                # Ignorar arquivos .gitignore
                if os.path.isfile(item_path) and not item.endswith('.gitignore'):
                    os.unlink(item_path)
                elif os.path.isdir(item_path):
                    shutil.rmtree(item_path)

    log_success("Arquivos de cache do Laravel removidos.")


def clean_vendor_directory():
    """Remove o diretório vendor do Composer."""
    log_info("Removendo diretório vendor...")
    vendor_dir = "multigateway-app/vendor"
    if os.path.exists(vendor_dir):
        shutil.rmtree(vendor_dir)
    log_success("Diretório vendor removido.")


def main():
    """Função principal do script."""
    # Banner
    print(f"{Colors.RED_BG}=============================================")
    print("      Docker System Cleanup Tool")
    print(f"============================================={Colors.RESET}")

    # Verificar qual comando do Docker Compose usar
    docker_compose = check_docker_compose()
    log_success(f"Usando comando: {docker_compose}")

    # Confirmar limpeza completa
    print(f"{Colors.RED_BG}⚠️  ATENÇÃO! ⚠️{Colors.RESET}")
    print("Esta ação irá:")
    print("1. Parar todos os contêineres em execução")
    print("2. Remover todos os contêineres, redes e volumes do projeto")
    print("3. Limpar imagens não utilizadas")
    print("4. Remover volumes Docker órfãos")
    print("")

    confirmation = input("Tem certeza que deseja continuar? (S/n): ")
    if confirmation.lower() not in ["s", ""]:
        log_warning("Operação cancelada pelo usuário.")
        sys.exit(0)

    # Parar todos os contêineres associados ao projeto
    log_info("Parando todos os contêineres do projeto...")
    run_command(f"{docker_compose} down --remove-orphans")
    log_success("Contêineres parados com sucesso.")

    # Remover todos os contêineres, redes e volumes deste projeto
    log_info("Removendo todos os recursos do projeto...")
    run_command(f"{docker_compose} down -v --remove-orphans")
    log_success("Recursos do projeto removidos.")

    # Limpar volumes Docker órfãos
    log_info("Removendo volumes órfãos...")
    # Obter lista de volumes órfãos
    result = run_command("docker volume ls -qf dangling=true", check=False)
    if result and result.stdout.strip():
        run_command(f"docker volume rm {result.stdout.strip()}", check=False)
    log_success("Volumes órfãos removidos.")

    # Opção para remover todas as imagens não utilizadas
    remove_images = input(
        "Deseja remover também todas as imagens não utilizadas? (s/N): ")
    if remove_images.lower() == "s":
        log_info("Removendo imagens não utilizadas...")
        run_command("docker image prune -af")
        log_success("Imagens não utilizadas removidas.")
    else:
        log_info("Mantendo imagens não utilizadas.")

    # Limpar arquivos de cache locais
    clean_laravel_option = input(
        "Deseja limpar os arquivos de cache do Laravel (bootstrap/cache, storage/framework)? (s/N): "
    )
    if clean_laravel_option.lower() == "s":
        clean_laravel_cache()

    # Limpar dependências do Composer (vendor)
    clean_vendor_option = input(
        "Deseja remover o diretório vendor do Composer? (s/N): ")
    if clean_vendor_option.lower() == "s":
        clean_vendor_directory()

    # Verificar e exibir espaço liberado
    try:
        log_info("Espaço em disco atual:")
        run_command("df -h .")
    except Exception:
        pass

    print(f"\n{Colors.GREEN_BG} LIMPEZA CONCLUÍDA COM SUCESSO! {Colors.RESET}\n")
    print(f"{Colors.CYAN}=====================================")
    print("      PRÓXIMOS PASSOS")
    print(f"====================================={Colors.RESET}")
    print("Para reconstruir o ambiente, execute:")
    print("  python setup.py")
    print(f"{Colors.CYAN}====================================={Colors.RESET}\n")


if __name__ == "__main__":
    main()
