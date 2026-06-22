# ERP no Oracle VirtualBox — do zero (pt-BR primeiro, SSH depois)

Ordem correta: **configurar a VM e o Ubuntu em português** → **testar teclado** → **só então** conectar do Windows via SSH/PowerShell.

---

## FASE 1 — Configurar a máquina virtual (só VirtualBox, sem SSH)

### 1.1 Baixar o Ubuntu

Recomendado para teclado BR e interface amigável:

**Ubuntu Desktop 24.04 LTS** (não Server):  
https://ubuntu.com/download/desktop

- Instalador em **português**
- Teclado **Português (Brasil)** / ABNT2 na interface gráfica
- Você usa o ERP no **navegador** — o desktop só ajuda a configurar a VM

*(Se preferir só terminal: Ubuntu Server 24.04 também funciona; use o instalador em português e marque OpenSSH.)*

---

### 1.2 Criar a VM no VirtualBox

**Máquina → Nova**

| Campo | Valor |
|-------|--------|
| Nome | `Erp-Acesso` |
| Pasta | padrão |
| ISO | Ubuntu 24.04 Desktop |
| Tipo | Linux |
| Versão | Ubuntu (64-bit) |
| RAM | **4096 MB** (mínimo 2 GB) |
| Disco | **30 GB**, dinâmico (VDI) |

**Antes de iniciar — Configurações da VM:**

#### Geral → Avançado
- **Área de transferência compartilhada:** Bidirecional  
- **Arrastar e soltar:** Bidirecional  

#### Sistema
- **Processador:** 2 CPUs  
- **Ordem de boot:** Óptico primeiro, depois disco  

#### Tela
- **Memória de vídeo:** 128 MB  

#### Rede → Placa 1
- **Modo de acesso:** Placa em modo bridge (ponte)  
- **Nome:** sua placa **Ethernet** (Wi‑Fi no bridge às vezes falha no Windows)

> Se o bridge com Wi‑Fi não funcionar depois, use **NAT** na Placa 1 e configure encaminhamento de porta (Fase 2.2).

#### Armazenamento
- Confirme que o ISO do Ubuntu está no controlador IDE/SATA

---

### 1.3 Instalar o Ubuntu (dentro da VM)

1. **Iniciar** a VM  
2. Idioma: **Português**  
3. Teclado: **Português (Brasil)** — layout **ABNT2**  
4. Instalação: **Ubuntu Desktop** (apagar disco e instalar — disco virtual vazio)  
5. Criar usuário:
   - Nome: `jose` (ou o que preferir)
   - Senha: anote  
6. Marque **Instalar software de terceiros** (drivers/Wi‑Fi) se aparecer  
7. Aguarde e **reinicie** quando pedir  
8. Remova o ISO: Configurações → Armazenamento → ejetar disco  

---

### 1.4 Teclado pt-BR (confirme na interface gráfica)

No Ubuntu (janela da VM):

1. **Configurações** → **Teclado** → **Layout de teclado**  
2. Adicione **Português (Brasil)**  
3. Remova **English (US)** se quiser só BR  
4. Teste em um editor de texto:

```
: / \ @ ç ã õ
```

Se estiver errado: em Teclado, escolha variante **ABNT2**.

---

### 1.5 Guest Additions (melhora mouse, tela e área de transferência)

No menu da janela VirtualBox (com a VM ligada):

**Dispositivos → Inserir imagem de CD dos Guest Additions**

No terminal do Ubuntu (Ctrl+Alt+T):

```bash
sudo apt update
```

```bash
sudo apt install -y build-essential dkms linux-headers-$(uname -r)
```

```bash
sudo mount /dev/cdrom /mnt
```

```bash
sudo /mnt/VBoxLinuxAdditions.run
```

```bash
sudo reboot
```

Após reiniciar: redimensione a janela da VM — a tela deve acompanhar.

---

### 1.6 Instalar e ativar SSH (ainda **sem** usar o PowerShell)

No Ubuntu:

```bash
sudo apt install -y openssh-server
```

```bash
sudo systemctl enable ssh
```

```bash
sudo systemctl start ssh
```

```bash
sudo systemctl status ssh
```

Deve aparecer **active (running)**.

---

### 1.7 Anotar o IP da VM (ainda dentro da VM)

```bash
hostname -I
```

Anote o primeiro número (ex.: `192.168.0.45`). Esse é o **IP_VM**.

Teste internet:

```bash
ping -c 2 8.8.8.8
```

```bash
ping -c 2 google.com
```

Se `google.com` falhar mas `8.8.8.8` funcionar, configure DNS:

```bash
sudo bash -c 'echo -e "nameserver 8.8.8.8\nnameserver 1.1.1.1" > /etc/resolv.conf'
```

---

## FASE 2 — Conectar do Windows (PowerShell + SSH)

Só comece esta fase quando o teclado e o SSH estiverem ok **dentro** da VM.

### 2.1 Testar ping

```powershell
ping IP_VM
```

Se não responder e a VM usar **NAT**, configure encaminhamento no VirtualBox:

- Configurações da VM → Rede → Avançado → **Encaminhamento de portas**
- Regra: `2222` → IP interno da VM porta `22`, TCP

Aí o SSH será:

```powershell
ssh -p 2222 jose@127.0.0.1
```

### 2.2 Primeiro SSH

```powershell
ssh jose@IP_VM
```

(ou `ssh -p 2222 jose@127.0.0.1` com NAT)

Na primeira vez digite `yes` e a senha.

---

## FASE 3 — Instalar o ERP (depois do SSH funcionar)

### 3.1 Pasta do projeto na VM

```bash
sudo mkdir -p /var/www/ERP-Acesso
sudo chown -R jose:www-data /var/www/ERP-Acesso
sudo chmod -R u=rwX,g=rX,o= /var/www/ERP-Acesso
```

> O grupo `www-data` precisa **ler** o código (`bootstrap/app.php`, etc.). Não use `jose:jose` sozinho — o PHP-FPM roda como `www-data`.

### 3.2 Enviar código do PC

```powershell
cd C:\Users\User\Documents\ERP_Acesso
powershell -ExecutionPolicy Bypass -File deploy\windows\atualizar.ps1 -VmHost IP_VM
```

Com NAT + porta 2222, edite temporariamente o script ou use `scp` manual com `-P 2222`.

### 3.3 Setup do servidor

```bash
cd /var/www/ERP-Acesso
sudo sed -i 's/\r$//' deploy/scripts/*.sh
sudo bash deploy/scripts/setup-ubuntu-vm.sh SUA_SENHA_FORTE_DB
```

### 3.4 `.env` e banco

```bash
cp deploy/env/.env.production.example .env
nano .env
```

Ajuste `APP_URL` e `DB_PASSWORD`, depois:

```bash
php artisan key:generate
php artisan migrate --force --seed
php artisan storage:link
```

### 3.5 Nginx e serviços

```bash
sudo sed "s/192.168.5.6/IP_VM/g" deploy/nginx/erp-acesso.conf | sudo tee /etc/nginx/sites-available/erp-acesso.conf
sudo ln -sf /etc/nginx/sites-available/erp-acesso.conf /etc/nginx/sites-enabled/
sudo rm -f /etc/nginx/sites-enabled/default
sudo nginx -t && sudo systemctl reload nginx
sudo APP_PATH=/var/www/ERP-Acesso bash deploy/scripts/instalar-servicos.sh
sudo rm -f bootstrap/cache/*.php
sudo -u www-data php artisan package:discover
sudo -u www-data php artisan config:cache
```

### 3.6 Abrir no navegador do Windows

**http://IP_VM**

Login: `admin@acesso.local` / `Acesso@2026`

---

## Checklist — você está pronto para o SSH quando:

- [ ] Ubuntu instalado em **português**
- [ ] Teclado **ABNT2** testado (`:`, `/`, `\`)
- [ ] Guest Additions instalado
- [ ] `sudo systemctl status ssh` → **active**
- [ ] `hostname -I` anotado
- [ ] `ping google.com` funciona na VM

---

## Atualizações futuras

```powershell
powershell -ExecutionPolicy Bypass -File deploy\windows\atualizar.ps1 -VmHost IP_VM
```

---

## VM Hyper-V antiga

Pode desligar e ignorar. Para dados antigos, só migre backup se precisar (opcional).
