<h1>CART CONTENTS</h1>

<div class="dm42cart catalog" style="width:100%;">
  <div class='section-title-wrapper'>
    <div class="title">{{if $title}}{{$title}}{{else}}Order{{/if}}</div>
  </div>
  <div class='section-content-wrapper' style="width:100%;">
    <table style="width:100%;">
        <tr>
            <th width=60%>Description</th>
            <th width=20% style="text-align:right;">Price each {{if $currencysymbol}}({{$currencysymbol}}){{/if}}</th>
            <th width=20% style="text-align:right;">Extended</th>
        </tr>
    {{foreach $items as $item}}
        <tr {{if $item.item_exeption}} class="cart-item-exception"{{/if}}>
            <td {{if $item.item_exeption}} class="cart-item-exception"{{/if}}>{{$item.item_desc}}
            {{include "./myshop_order_item_xtrahtml.tpl"}}
            </td>
            <td style="text-align:right;" {{if $item.item_exeption}} class="cart-item-exception"{{/if}}>{{$item.item_price}}</td>
            <td style="text-align:right;" {{if $item.item_exeption}} class="cart-item-exception"{{/if}}>{{$item.extended}}</td>
        </tr>
    {{/foreach}}
    <tr>
        <td></td>
        <th style="text-align:right;">Subtotal</th>
        <td style="text-align:right;">{{$totals.Subtotal}}</td>
    </tr>
    <tr>
        <td></td>
        <th style="text-align:right;">Tax Total</th>
        <td style="text-align:right;">{{$totals.Tax}}</td>
    </tr>
    <tr>
        <td></td>
        <th style="text-align:right;">Order Total</th>
        <td style="text-align:right;">{{$totals.OrderTotal}}</td>
    </tr>
    {{if $totals.Payment}}
    <tr>
        <td></td>
        <th>Payment</th>
        <td style="text-align:right;">{{$totals.Payment}}</td>
    </tr>
    {{/if}}
    </table>
    <div>
      {{if !$order.order_paid}}
      <form method="post">
        <input type=hidden name="security" value="{{$security_token}}">
        <input type=hidden name="cart_posthook" value="myshop_order">
        <input type=hidden name="orderhash" value="{{$order_hash}}">
        <input type=hidden name="action" value="markpaid">
        <button class="btn btn-primary" type="submit" name="Confirm" id="cart-payment-button" class="cart-payment-button" value="Confirm">Mark Paid</button>
      </form>
      {{/if}}
      <hr>
      <h3>Record Manual Payment</h3>
      <hr>
      <h3>Order Notes</h3>
      <hr>
      <h3>Add Order Note</h3>
    </div>
  </div>
</div>
