/**
 * @file
 * Global utilities.
 *
 */
(function ($, Drupal) {

  'use strict';

  Drupal.behaviors.firjan_mercadolivre = {
    attach: function (context, settings) {
      if (context !== document) return;

      var init = function () {
        $("body").on("click", ".firjan-accordion-item", function(e){
          $(this).toggleClass('show')
        })      
        
        $("body").on("click", ".arrow", function(e){
          $(this).closest('.accordion-item').find('.accordion-content').toggle()
          $(this).toggleClass('--on')
        })    

        new ModalVideo('.js-modal-btn');
        
        var modals = document.querySelector('.block-form').querySelectorAll('.modal');
        var current = 0;
        var enableLogin = 0;
        
        $('[name*="cnpj"], [class*="cnpj"]').mask('0#');    

        var owl = $('.owl-form').owlCarousel({
          loop:false,
          nav:false,
          autoplay: false,
          dots:false,
          items: 1,
          autoHeight:true,
          autoHeightClass: 'owl-height',
          mouseDrag:false,
          animateIn: 'false',
          touchDrag:false,
          pullDrag:false,
          freeDrag:false,
        });

        owl.on('changed.owl.carousel', function (e) {
          current = e.item.index
        })

        // Play carousel && check errors
        setTimeout(() => {
          if (modals && modals.length) {
            owl.trigger('play.owl.carousel');
            
            for (let i = 0; i < modals.length; i++) {
              modals[i].style.opacity = 1
            }

            for (let index = 0; index < document.getElementsByClassName('error').length; index++) {
              owl.trigger('to.owl.carousel', [2, 500, true]);
            }            
          }
        }, 500)

        function changePanel(e) {
          let page = e.target.dataset.page;
          
          $('.form-item--error-message');

          owl.trigger('to.owl.carousel', [page, 0, true]);

          var element = document.getElementsByTagName("form");
          for (let index = 0; index < element.length; index++) {
            const el = element[index];
            el.reset()
          };          

          let info = {
              title: '',
              subtitle: '',
              loginLabel: '',
              footer: ''
          }

          switch (parseInt(page)) {
            case 0:
              info.title = 'Login'
              info.subtitle = 'Os dados de acesso estarão no seu e-mail, insira os abaixo e acesse a área interna:'
              info.loginLabel = 'Cadastre-se aqui.'
              info.footer = 'Não tem cadastro ainda?'             
              for (let index = 0; index < document.getElementsByClassName('modal_toggler').length; index++) {
                const button = document.getElementsByClassName('modal_toggler')[index];
                if(button.dataset.page == '0') {
                  button.setAttribute('data-page', 1)
                  button.innerHTML = info.loginLabel
                }
              }
              break;            
            case 2:
            case 1:
              // CNPJ & Register
              info.title = 'Cadastro'
              info.loginLabel = 'Faça o login.'
              info.subtitle = 'Para prosseguir, insira seu CNPJ e veja se é elegível para cadastro.'
              info.footer = 'Deseja fazer o login?'
              for (let index = 0; index < document.getElementsByClassName('modal_toggler').length; index++) {
                const button = document.getElementsByClassName('modal_toggler')[index];
                if(button.dataset.page == '1') {
                  button.setAttribute('data-page', 0)
                  button.innerHTML = info.loginLabel
                }
              }              
              break;
            case 2:
              // Register
              info.subtitle = ''
              break;
            case 3:
              // Reset
              info.title = 'Resetar senha'
              info.loginLabel = 'Faça o login.'
              info.subtitle = 'As informações de recuperação de senha irão para o e-mail cadastrado.'
              info.footer = 'Deseja fazer o login?'         
              for (let index = 0; index < document.getElementsByClassName('modal_toggler').length; index++) {
                const button = document.getElementsByClassName('modal_toggler')[index];
                if(button.dataset.page == '1') {
                  button.setAttribute('data-page', 0)
                  button.innerHTML = info.loginLabel
                }
              }
              break;                        
          }

          document.getElementsByClassName('wrapper-title')[0].innerHTML = info.title;
          document.getElementsByClassName('wrapper-subtitle')[0].innerHTML = info.subtitle;
          document.getElementsByClassName('wrapper-footer--description')[0].childNodes[1].childNodes[1].innerHTML = info.footer;
        }

        $("body").on("click", ".modal_toggler", function(e){
          changePanel(e);
        })

        // Requisição Watcher
        $( document ).ajaxStart(function( event, xhr, settings ) {
          $('.spinner').removeClass('d-none').addClass('d-flex') 
        }).ajaxComplete(function( event, xhr, settings ) {
          $('.spinner').removeClass('d-flex').addClass('d-none') 
        });

        // Login
        $(document).on('submit', '[data-drupal-selector="user-login-form"]', function (e) {
          e.preventDefault();
          let $this = this;

          let name = $(this).serialize().split('&').find(prop => prop.indexOf('name') >= 0).split('=')[1];
          let pass = $(this).serialize().split('&').find(prop => prop.indexOf('pass') >= 0).split('=')[1];

          $.ajax({
            method: "POST",
            url: `/user/login?_format=json`,
            contentType: "application/json; charset=utf-8",
            data : JSON.stringify({
              "name": name,
              "pass": pass
            }),            
          }).done(function(response) {
            console.log(response)
            
            enableLogin = 1;
            window.location = '/';           
          }).fail(function(err) {
            let error = '';

            if(name == 'admin') {
              error = err.status == 400 ? 'Usuário ou senha incorretos.' : err.responseJSON.message
            } else {
                error = err.status == 400 ? 'Usuário ou senha incorretos. Para acessar a plataforma primeiro <a class="modal_toggler" data-page="1" rel="modal_toggler" href="javascript:void(0)">Cadastre-se aqui.</a>' : err.responseJSON.message
            }

            let html = `<div class="form-item--error-message mt-2">${error}</div>`;
            
            if(!$('.form-item--error-message').length) {
              $('#edit-name').parent().append(html);
            } else {
              $('#edit-name').next()[0].innerHTML = html
              // $('#edit-name').next().innerHTML(html);
            }

            owl.trigger('refresh.owl.carousel');

            enableLogin = 0; 
          });           
        });              

        // Pré Cadastro
        $(document).on('submit', 'form.cpf', function (e) {
            e.preventDefault();
            $('.form-item--error-message').remove();
            $.post(`//api.firjan-ml.cityconnect.com.br/Firjan.Sistemas.Competitividade.ConectaEmpresas.API/Logins/index.php`, {
              "cnpj": $(this).serialize().split('=')[1]
            }, function(response){ 
                let login = $('[name*="field-cnpj"]').val();
                
                if(response) {
                    console.log(response)
                    
                    if(response.name == 'SIND' || response.name == 'CIRJ') {
                      $("#edit-name--2").val(login).attr('readonly', true);
                      if(response.nome_da_Empresa) $("#edit-field-nome-0-value").val(response.nome_da_Empresa).attr('readonly', true);
                      owl.trigger('to.owl.carousel', [2, 0, true]);
                      owl.trigger('refresh.owl.carousel');   
                    } else {
                      $("#edit-name--2", "#edit-field-nome-0-value").val('').attr('readonly', false);
        
                      let html = `<div class="form-item--error-message mt-2">CNPJ ${login} inelegível para cadastro</div>`;
                      
                      if (!$('.form-item--error-message').length) $('[name="field-cnpj"]').parent().append(html);
                      
                      owl.trigger('refresh.owl.carousel');  
                    }
                } else {
                      $("#edit-name--2", "#edit-field-nome-0-value").val('').attr('readonly', false);
        
                      let html = `<div class="form-item--error-message mt-2">Ocorreu um erro na requisição</div>`;
                      
                      if (!$('.form-item--error-message').length) $('[name="field-cnpj"]').parent().append(html);
                      
                      owl.trigger('refresh.owl.carousel');                      
                }
            });
        });        
      }

      window.onload = init;

      $('.owl-content').owlCarousel({
        loop:false,
        nav:true,
        autoplay: false,
        dots:false,
        items: 1,
        margin: 15,
        autoHeight:true,
        autoHeightClass: 'owl-height',
        responsive : {
            768 : {
              items: 2,
            },
            1024 : {
              items: 3,
            }
        }          
      });              
    }
  };
})(jQuery, Drupal);
