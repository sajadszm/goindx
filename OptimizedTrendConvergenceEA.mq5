//+------------------------------------------------------------------+
//|                                 OptimizedTrendConvergenceEA.mq5 |
//|                                     Developed by Jules (AI)     |
//|                                      https://www.example.com    |
//+------------------------------------------------------------------+
#property copyright "Copyright 2023, Your Name & Co."
#property link      "https://www.example.com"
#property version   "1.00"
#property description "A robust trend-following Expert Advisor using EMA convergence and an RSI momentum filter for high-probability entries."

//--- Include the Standard Library for Trade Functions
#include <Trade\Trade.mqh>

//--- EA Input Parameters ---
// These inputs allow for full customization of the EA's strategy and risk settings.

// Trend Detection Module Settings
input group           "Trend Detection Settings";
input int             EMA_Fast_Period = 20;            // Period for the Fast Exponential Moving Average.
input int             EMA_Slow_Period = 50;            // Period for the Slow Exponential Moving Average.

// Momentum Filter Module Settings
input group           "Momentum Filter Settings";
input int             RSI_Period = 14;                 // Period for the Relative Strength Index.
input double          RSI_Oversold_Level = 30.0;       // RSI level below which the market is considered oversold.
input double          RSI_Overbought_Level = 70.0;     // RSI level above which the market is considered overbought.

// Position Management Module Settings
input group           "Position Management Settings";
enum ENUM_SL_MODE
{
   slm_CandleHighLow, // Stop Loss based on the Signal Candle's High/Low
   slm_FixedPips      // Stop Loss based on a Fixed number of pips
};
input ENUM_SL_MODE    Stop_Loss_Mode = slm_CandleHighLow;    // Choose the Stop Loss calculation method.
input int             Fixed_Stop_Loss_Pips = 30;           // Stop Loss in pips (only used if SL Mode is FixedPips).
input int             SL_Buffer_Pips = 1;                  // Extra pips to add to the SL for spread/slippage buffer.
input double          Take_Profit_Ratio = 1.5;             // The Take Profit distance as a multiple of the Stop Loss distance (e.g., 1.5 means 1.5:1 R:R).
input int             Trailing_Stop_Pips = 10;             // Distance in pips to trail the stop loss behind the current price.
input int             Breakeven_Pips = 10;                 // Profit in pips at which the stop loss is moved to the entry price.

// Risk Management Module Settings
input group           "Risk Management Settings";
input double          Risk_Percentage = 1.5;           // Percentage of the account balance to risk on a single trade.
input ulong           Magic_Number = 12345;            // A unique ID to ensure the EA only manages its own trades.

// Notification System Settings
input group           "Notification Settings";
input bool            Send_Email_Alerts = true;        // Set to true to receive email alerts for new signals.
input bool            Show_Popup_Alerts = true;        // Set to true to receive on-screen pop-up alerts with sound.


//--- Global Variables ---
CTrade trade;                  // Trading object from the standard library to simplify trade operations.
int    ema_fast_handle;        // Handle for the fast EMA indicator.
int    ema_slow_handle;        // Handle for the slow EMA indicator.
int    rsi_handle;             // Handle for the RSI indicator.

//+------------------------------------------------------------------+
//| Expert Initialization Function                                   |
//| Called once when the EA is first attached to a chart.            |
//+------------------------------------------------------------------+
int OnInit()
{
   //--- Setup the trading object
   trade.SetExpertMagicNumber(Magic_Number);
   trade.SetMarginMode(); // Use the account's default margin calculation mode.

   printf("Initializing EA '%s' on %s, %s...", MQL5InfoString(MQL5_PROGRAM_NAME), _Symbol, EnumToString(_Period));

   //--- Initialize Fast EMA Indicator
   ema_fast_handle = iMA(_Symbol, _Period, EMA_Fast_Period, 0, MODE_EMA, PRICE_CLOSE);
   if(ema_fast_handle == INVALID_HANDLE)
   {
      printf("Error creating Fast EMA indicator handle - error #%d", GetLastError());
      return(INIT_FAILED);
   }

   //--- Initialize Slow EMA Indicator
   ema_slow_handle = iMA(_Symbol, _Period, EMA_Slow_Period, 0, MODE_EMA, PRICE_CLOSE);
   if(ema_slow_handle == INVALID_HANDLE)
   {
      printf("Error creating Slow EMA indicator handle - error #%d", GetLastError());
      return(INIT_FAILED);
   }

   //--- Initialize RSI Indicator
   rsi_handle = iRSI(_Symbol, _Period, RSI_Period, PRICE_CLOSE);
   if(rsi_handle == INVALID_HANDLE)
   {
      printf("Error creating RSI indicator handle - error #%d", GetLastError());
      return(INIT_FAILED);
   }

   //--- Initialization successful
   printf("EA Initialized Successfully. All indicators loaded.");
   return(INIT_SUCCEEDED);
}

//+------------------------------------------------------------------+
//| Expert Deinitialization Function                                 |
//| Called once when the EA is removed from the chart.               |
//+------------------------------------------------------------------+
void OnDeinit(const int reason)
{
   //--- Clean up indicator handles to free up terminal resources
   IndicatorRelease(ema_fast_handle);
   IndicatorRelease(ema_slow_handle);
   IndicatorRelease(rsi_handle);
   printf("EA Deinitialized. Resources released.");
}

//+------------------------------------------------------------------+
//| Expert Tick Function                                             |
//| Called on every new price tick for the chart's symbol.           |
//+------------------------------------------------------------------+
void OnTick()
{
   //--- Manage open positions on every tick to ensure timely SL adjustments (trailing/breakeven).
   ManagePositions();

   //--- Check for new trading signals only once per bar to conserve resources.
   static datetime last_bar_time = 0;
   datetime current_bar_time = (datetime)SeriesInfoInteger(_Symbol, _Period, SERIES_LAST_BAR_TIME);

   if(current_bar_time > last_bar_time)
   {
      last_bar_time = current_bar_time;

      //--- Check if auto-trading is enabled and if there are no open positions before looking for a new signal.
      if(IsTradeAllowed() && PositionsTotal() == 0)
      {
         CheckForSignal();
      }
   }
}

//+------------------------------------------------------------------+
//| Check For Signal                                                 |
//| Contains the core logic for identifying buy and sell signals.    |
//+------------------------------------------------------------------+
void CheckForSignal()
{
   //--- We need historical data for the last 3 completed bars.
   // Bar at index 1: The "signal candle" that just closed. Conditions are checked on this bar.
   // Bar at index 2: The candle prior to the signal candle, used for RSI crossover detection.

   double ema_fast_buffer[3];
   double ema_slow_buffer[3];
   double rsi_buffer[3];
   MqlRates price_buffer[3];

   //--- Request data from the server for the last 3 completed bars (starting from index 1).
   if(CopyBuffer(ema_fast_handle, 1, 3, ema_fast_buffer) < 3 ||
      CopyBuffer(ema_slow_handle, 1, 3, ema_slow_buffer) < 3 ||
      CopyBuffer(rsi_handle, 1, 3, rsi_buffer) < 3 ||
      CopyRates(_Symbol, _Period, 1, 3, price_buffer) < 3)
   {
      printf("Error: Could not copy indicator or price data for signal check. Not enough history?");
      return;
   }

   //--- Developer Note on Data Indexing:
   // The CopyBuffer/CopyRates functions copy data from the past towards the present (chronologically).
   // When requesting 3 bars starting from index 1 (the most recently closed bar), the data is returned as follows:
   // buffer[0] = Data for bar at index 3 (Oldest)
   // buffer[1] = Data for bar at index 2
   // buffer[2] = Data for bar at index 1 (Newest, the signal candle)
   // This is the standard MQL5 behavior. Therefore, we access buffer[2] for the signal candle.

   //--- Extract data for the Signal Candle (the most recently closed bar)
   MqlRates signal_candle_info = price_buffer[2];
   double fast_ema_signal = ema_fast_buffer[2];
   double slow_ema_signal = ema_slow_buffer[2];
   double rsi_signal = rsi_buffer[2];
   double close_signal = signal_candle_info.close;

   //--- Extract data for the Previous Candle (for RSI crossover)
   double rsi_previous = rsi_buffer[1];

   //====== Buy Signal Logic ======
   // 1. Trend Confirmation: Fast EMA is above Slow EMA, and the close price is above both EMAs.
   bool is_uptrend = fast_ema_signal > slow_ema_signal && close_signal > fast_ema_signal && close_signal > slow_ema_signal;
   // 2. Momentum Confirmation: RSI was below the oversold level and has now crossed back above it.
   bool is_buy_momentum = rsi_previous < RSI_Oversold_Level && rsi_signal > RSI_Oversold_Level;

   if(is_uptrend && is_buy_momentum)
   {
      string message = StringFormat("%s: Buy Signal on %s.", _Symbol, EnumToString(_Period));
      if(Show_Popup_Alerts) Alert(message);
      if(Send_Email_Alerts) SendMail(StringFormat("%s Buy Signal", _Symbol), message);

      ExecuteBuy(signal_candle_info);
      return; // Exit after processing the signal
   }

   //====== Sell Signal Logic ======
   // 1. Trend Confirmation: Fast EMA is below Slow EMA, and the close price is below both EMAs.
   bool is_downtrend = fast_ema_signal < slow_ema_signal && close_signal < fast_ema_signal && close_signal < slow_ema_signal;
   // 2. Momentum Confirmation: RSI was above the overbought level and has now crossed back below it.
   bool is_sell_momentum = rsi_previous > RSI_Overbought_Level && rsi_signal < RSI_Overbought_Level;

   if(is_downtrend && is_sell_momentum)
   {
      string message = StringFormat("%s: Sell Signal on %s.", _Symbol, EnumToString(_Period));
      if(Show_Popup_Alerts) Alert(message);
      if(Send_Email_Alerts) SendMail(StringFormat("%s Sell Signal", _Symbol), message);

      ExecuteSell(signal_candle_info);
      return; // Exit after processing the signal
   }
}

//+------------------------------------------------------------------+
//| Execute Buy Trade                                                |
//| Handles the execution of a buy order with full risk management.  |
//+------------------------------------------------------------------+
void ExecuteBuy(const MqlRates &signal_candle)
{
   double pip_size = GetPipSize();
   double entry_price = SymbolInfoDouble(_Symbol, SYMBOL_ASK);
   double sl_price;

   //--- Calculate SL based on the selected mode
   if(Stop_Loss_Mode == slm_CandleHighLow)
   {
      // Stop Loss is placed below the low of the signal candle, plus a buffer.
      sl_price = signal_candle.low - (SL_Buffer_Pips * pip_size);
   }
   else // slm_FixedPips
   {
      // Stop Loss is placed at a fixed pip distance from the entry price.
      sl_price = entry_price - (Fixed_Stop_Loss_Pips * pip_size);
   }

   double stop_loss_in_pips = (entry_price - sl_price) / pip_size;
   if(stop_loss_in_pips <= 0) {
      printf("Invalid SL distance for Buy. Entry: %.5f, SL: %.5f. Check SL settings.", entry_price, sl_price);
      return;
   }

   //--- Take Profit is calculated based on the SL distance and the R:R ratio.
   double tp_price = entry_price + (stop_loss_in_pips * Take_Profit_Ratio * pip_size);

   //--- Calculate lot size based on risk percentage and stop loss distance.
   double lot_size = CalculateLotSize(ORDER_TYPE_BUY, sl_price);
   if(lot_size <= 0) {
      printf("Trade execution skipped due to invalid lot size (%.2f).", lot_size);
      return;
   }

   //--- Execute the trade using the CTrade object.
   printf("Executing BUY: Lot=%.2f, Entry=%.5f, SL=%.5f, TP=%.5f", lot_size, entry_price, sl_price, tp_price);
   trade.Buy(lot_size, _Symbol, entry_price, sl_price, tp_price, "Buy by OptiTrendEA");
   if(trade.ResultRetcode() != TRADE_RETCODE_DONE)
   {
      printf("Buy order failed. Error: %d - %s", trade.ResultRetcode(), trade.ResultComment());
   }
   else
   {
      printf("Buy order placed successfully. Ticket #%d", (int)trade.ResultOrder());
   }
}

//+------------------------------------------------------------------+
//| Execute Sell Trade                                               |
//| Handles the execution of a sell order with full risk management. |
//+------------------------------------------------------------------+
void ExecuteSell(const MqlRates &signal_candle)
{
   double pip_size = GetPipSize();
   double entry_price = SymbolInfoDouble(_Symbol, SYMBOL_BID);
   double sl_price;

   //--- Calculate SL based on the selected mode
   if(Stop_Loss_Mode == slm_CandleHighLow)
   {
      // Stop Loss is placed above the high of the signal candle, plus a buffer.
      sl_price = signal_candle.high + (SL_Buffer_Pips * pip_size);
   }
   else // slm_FixedPips
   {
      // Stop Loss is placed at a fixed pip distance from the entry price.
      sl_price = entry_price + (Fixed_Stop_Loss_Pips * pip_size);
   }

   double stop_loss_in_pips = (sl_price - entry_price) / pip_size;
   if(stop_loss_in_pips <= 0) {
      printf("Invalid SL distance for Sell. Entry: %.5f, SL: %.5f. Check SL settings.", entry_price, sl_price);
      return;
   }

   //--- Take Profit is calculated based on the SL distance and the R:R ratio.
   double tp_price = entry_price - (stop_loss_in_pips * Take_Profit_Ratio * pip_size);

   //--- Calculate lot size based on risk percentage and stop loss distance.
   double lot_size = CalculateLotSize(ORDER_TYPE_SELL, sl_price);
   if(lot_size <= 0) {
      printf("Trade execution skipped due to invalid lot size (%.2f).", lot_size);
      return;
   }

   //--- Execute the trade using the CTrade object.
   printf("Executing SELL: Lot=%.2f, Entry=%.5f, SL=%.5f, TP=%.5f", lot_size, entry_price, sl_price, tp_price);
   trade.Sell(lot_size, _Symbol, entry_price, sl_price, tp_price, "Sell by OptiTrendEA");
   if(trade.ResultRetcode() != TRADE_RETCODE_DONE)
   {
      printf("Sell order failed. Error: %d - %s", trade.ResultRetcode(), trade.ResultComment());
   }
   else
   {
      printf("Sell order placed successfully. Ticket #%d", (int)trade.ResultOrder());
   }
}

//+------------------------------------------------------------------+
//| Manage Positions                                                 |
//| Handles Breakeven and Trailing Stop logic for open trades.       |
//+------------------------------------------------------------------+
void ManagePositions()
{
   double pip_size = GetPipSize();

   //--- Loop through all open positions, from last to first, to avoid index issues on close.
   for(int i = PositionsTotal() - 1; i >= 0; i--)
   {
      ulong ticket = PositionGetTicket(i);
      if(ticket > 0)
      {
         //--- Filter to only manage trades opened by this EA on this symbol.
         if(PositionGetInteger(POSITION_MAGIC) == Magic_Number && PositionGetString(POSITION_SYMBOL) == _Symbol)
         {
            long   type         = PositionGetInteger(POSITION_TYPE);
            double open_price   = PositionGetDouble(POSITION_PRICE_OPEN);
            double current_sl   = PositionGetDouble(POSITION_SL);
            double current_tp   = PositionGetDouble(POSITION_TP);

            if(type == POSITION_TYPE_BUY)
            {
               double current_price = SymbolInfoDouble(_Symbol, SYMBOL_BID);
               double profit_pips = (current_price - open_price) / pip_size;

               //--- Breakeven Logic: If profit hits the target and SL is not yet at breakeven.
               if(current_sl < open_price && profit_pips >= Breakeven_Pips)
               {
                  if(trade.PositionModify(ticket, open_price, current_tp))
                  {
                     printf("Position #%d: Moved SL to Breakeven at %.5f", (int)ticket, open_price);
                  }
                  else
                  {
                     printf("Position #%d: Failed to move SL to Breakeven. Error: %d - %s", (int)ticket, trade.ResultRetcode(), trade.ResultComment());
                  }
                  continue; // After modification, skip to the next position.
               }

               //--- Trailing Stop Logic: If SL is already at or past breakeven.
               if(current_sl >= open_price)
               {
                  double new_sl = current_price - (Trailing_Stop_Pips * pip_size);
                  //--- Only move the SL forward (up for a buy) to lock in more profit.
                  if(new_sl > current_sl)
                  {
                     if(trade.PositionModify(ticket, new_sl, current_tp))
                     {
                        printf("Position #%d: Trailed SL to %.5f", (int)ticket, new_sl);
                     }
                     else
                     {
                        printf("Position #%d: Failed to trail SL. Error: %d - %s", (int)ticket, trade.ResultRetcode(), trade.ResultComment());
                     }
                  }
               }
            }
            else if(type == POSITION_TYPE_SELL)
            {
               double current_price = SymbolInfoDouble(_Symbol, SYMBOL_ASK);
               double profit_pips = (open_price - current_price) / pip_size;

               //--- Breakeven Logic: If profit hits the target and SL is not yet at breakeven.
               if((current_sl > open_price || current_sl == 0) && profit_pips >= Breakeven_Pips)
               {
                  if(trade.PositionModify(ticket, open_price, current_tp))
                  {
                     printf("Position #%d: Moved SL to Breakeven at %.5f", (int)ticket, open_price);
                  }
                  else
                  {
                     printf("Position #%d: Failed to move SL to Breakeven. Error: %d - %s", (int)ticket, trade.ResultRetcode(), trade.ResultComment());
                  }
                  continue;
               }

               //--- Trailing Stop Logic: If SL is already at or past breakeven.
               if(current_sl <= open_price && current_sl != 0)
               {
                  double new_sl = current_price + (Trailing_Stop_Pips * pip_size);
                  //--- Only move the SL forward (down for a sell) to lock in more profit.
                  if(new_sl < current_sl)
                  {
                     if(trade.PositionModify(ticket, new_sl, current_tp))
                     {
                        printf("Position #%d: Trailed SL to %.5f", (int)ticket, new_sl);
                     }
                     else
                     {
                        printf("Position #%d: Failed to trail SL. Error: %d - %s", (int)ticket, trade.ResultRetcode(), trade.ResultComment());
                     }
                  }
               }
            }
         }
      }
   }
}

//+------------------------------------------------------------------+
//| Calculate Lot Size                                               |
//| A robust function to calculate trade volume based on risk %.     |
//+------------------------------------------------------------------+
double CalculateLotSize(ENUM_ORDER_TYPE order_type, double sl_price)
{
    //--- Get account balance
    double account_balance = AccountInfoDouble(ACCOUNT_BALANCE);
    if(account_balance <= 0)
    {
        printf("Invalid account balance: %.2f", account_balance);
        return 0.0;
    }
    //--- Calculate the amount to risk in the account's currency.
    double risk_amount = account_balance * (Risk_Percentage / 100.0);
    double entry_price = (order_type == ORDER_TYPE_BUY) ? SymbolInfoDouble(_Symbol, SYMBOL_ASK) : SymbolInfoDouble(_Symbol, SYMBOL_BID);

    //--- Use OrderCalcProfit to find the monetary loss for a 1.0 lot trade.
    // This is the most reliable way as it handles all currency conversions.
    double loss_for_one_lot = 0;
    if(!OrderCalcProfit(order_type, _Symbol, 1.0, entry_price, sl_price, loss_for_one_lot))
    {
        printf("Error calculating profit/loss for lot size: #%d", GetLastError());
        return 0.0;
    }

    //--- If loss is zero (e.g., invalid SL), we can't calculate lot size.
    if(MathAbs(loss_for_one_lot) <= 1e-10)
    {
        printf("Cannot calculate lot size. Potential loss for 1 lot is zero or invalid.");
        return 0.0;
    }

    //--- Calculate the ideal lot size.
    double lot_size = risk_amount / MathAbs(loss_for_one_lot);

    //--- Normalize the lot size according to the symbol's volume step (e.g., 0.01).
    double vol_step = SymbolInfoDouble(_Symbol, SYMBOL_VOLUME_STEP);
    lot_size = floor(lot_size / vol_step) * vol_step;

    //--- Clamp the lot size to the symbol's minimum and maximum allowed volume.
    double min_vol = SymbolInfoDouble(_Symbol, SYMBOL_VOLUME_MIN);
    double max_vol = SymbolInfoDouble(_Symbol, SYMBOL_VOLUME_MAX);
    if(lot_size < min_vol)
    {
        lot_size = min_vol;
    }
    if(lot_size > max_vol)
    {
        lot_size = max_vol;
    }

    //--- Final check: if the minimum lot size is still too risky, abort the trade.
    if (lot_size * MathAbs(loss_for_one_lot) > risk_amount && lot_size == min_vol)
    {
       printf("Cannot afford minimum lot size (%.2f) with current risk percentage (%.2f%%). No trade placed.", min_vol, Risk_Percentage);
       return 0.0;
    }

    return lot_size;
}

//+------------------------------------------------------------------+
//| Get Pip Size                                                     |
//| A helper function to determine the size of one pip for any symbol|
//+------------------------------------------------------------------+
double GetPipSize()
{
    // A pip is typically the 4th decimal place for Forex, or 2nd for JPY pairs.
    // This logic correctly handles 2, 3, 4, and 5-digit brokers.
    int digits = (int)SymbolInfoInteger(_Symbol, SYMBOL_DIGITS);
    if (digits == 3 || digits == 5 || digits == 1) // Handle 3/5 digit brokers and some commodities/indices
        return _Point * 10;
    return _Point;
}
//+------------------------------------------------------------------+
