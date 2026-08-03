// script.js

// ===== CARDÁPIO =====
fetch("cardapio.php")
.then(response => response.json())
.then(dados => {

    let area = document.getElementById("lista-cardapio");

    dados.forEach(item => {

        area.innerHTML += `
            <div>
                <h3>${item.prato}</h3>
                <p>${item.descricao}</p>
            </div>
        `;
    });

})
.catch(err => console.log("Cardápio não disponível"));

// ===== PRESENÇA =====
class RegistradorPresenca {
    constructor() {
        this.chaveStorage = "listaPresenca";
        this.carregarPresenca();
        this.inicializarEventos();
    }

    carregarPresenca() {
        const dados = localStorage.getItem(this.chaveStorage);
        this.presentes = dados ? JSON.parse(dados) : [];
    }

    salvarPresenca() {
        localStorage.setItem(this.chaveStorage, JSON.stringify(this.presentes));
    }

    adicionarPresenca(nome) {
        if (nome.trim() === "") return;
        
        // Evita duplicatas - nome não pode repetir
        if (this.presentes.some(p => p.nome.toLowerCase() === nome.toLowerCase())) {
            alert("❌ Nome não permitido! Essa pessoa já registrou presença.");
            document.getElementById("input-nome").value = "";
            return;
        }

        this.presentes.push({
            id: Date.now(),
            nome: nome.trim(),
            horario: new Date().toLocaleTimeString("pt-BR")
        });

        this.salvarPresenca();
        this.mostrarMensagem();
        document.getElementById("input-nome").value = "";
    }

    mostrarMensagem() {
        const msg = document.getElementById("msg-presenca");
        msg.style.display = "block";
        setTimeout(() => {
            msg.style.display = "none";
        }, 2000);
    }

    inicializarEventos() {
        const form = document.getElementById("form-presenca");
        form.addEventListener("submit", (e) => {
            e.preventDefault();
            const nome = document.getElementById("input-nome").value;
            this.adicionarPresenca(nome);
        });
    }
}

// Inicializar registrador quando a página carregar
const registrador = new RegistradorPresenca();
